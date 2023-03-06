<?php

namespace SV\EmailDomainValidation;

use Egulias\EmailValidator\EmailLexer;
use Egulias\EmailValidator\Exception\DomainAcceptsNoMail;
use Egulias\EmailValidator\Exception\InvalidEmail;
use Egulias\EmailValidator\Exception\LocalOrReservedDomain;
use Egulias\EmailValidator\Exception\NoDNSRecord;
use Egulias\EmailValidator\Warning\NoDNSMXRecord;
use function count;
use function dns_get_record;
use function explode;
use function function_exists;
use function in_array;
use function mb_strtolower;
use function rtrim;
use function sprintf;
use function strrpos;
use function substr;

/**
 * Copy Egulias\EmailValidator\Validation\DNSCheckValidation to add additional logic around MX checking
 */
class EmailValidation implements \Egulias\EmailValidator\Validation\EmailValidation
{
    public const BANNED_EMAIL_CODE = 'banned_email';

    /**
     * @var array<int,\Egulias\EmailValidator\Warning\Warning>
     */
    protected $warnings = [];

    /**
     * @var ?InvalidEmail
     */
    protected $error;

    /**
     * @var array<array>
     */
    protected $mxRecords = [];

    protected $xfBannedEmails;


    public function __construct($xfBannedEmails)
    {
        if (!function_exists('idn_to_ascii')) {
            throw new \LogicException(sprintf('The %s class requires the Intl extension.', __CLASS__));
        }

        $this->xfBannedEmails = $xfBannedEmails;
    }

    /**
     * @param string     $email
     * @param EmailLexer $emailLexer
     * @return bool
     */
    public function isValid($email, EmailLexer $emailLexer): bool
    {
        assert(is_string($email));
        // use the input to check DNS if we cannot extract something similar to a domain
        $host = $email;

        // Arguable pattern to extract the domain. Not aiming to validate the domain nor the email
        $lastAtPos = strrpos($email, '@');
        if ($lastAtPos === false)
        {
            return false;
        }

        $host = substr($email, $lastAtPos + 1);
        $localPart = substr($email, 0, $lastAtPos);

        $host = mb_strtolower($host);
        // Get the domain parts
        $hostParts = explode('.', $host);

        // Reserved Top Level DNS Names (https://tools.ietf.org/html/rfc2606#section-2),
        // mDNS and private DNS Namespaces (https://tools.ietf.org/html/rfc6762#appendix-G)
        $reservedTopLevelDnsNames = [
            // Reserved Top Level DNS Names
            'test',
            'example',
            'invalid',
            'localhost',

            // mDNS
            'local',

            // Private DNS Namespaces
            'intranet',
            'internal',
            'private',
            'corp',
            'home',
            'lan',
        ];

        $isLocalDomain = count($hostParts) <= 1;
        $isReservedTopLevel = in_array($hostParts[(count($hostParts) - 1)], $reservedTopLevelDnsNames, true);

        // Exclude reserved top level DNS names
        if ($isLocalDomain || $isReservedTopLevel) {
            $this->error = new LocalOrReservedDomain();
            return false;
        }

        return $this->checkDns($localPart, $host);
    }

    public function getError(): ?InvalidEmail
    {
        return $this->error;
    }

    /**
     * @return array<int,\Egulias\EmailValidator\Warning\Warning>
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    protected function checkDns(string $localPart, string $host): bool
    {
        $variant = INTL_IDNA_VARIANT_UTS46;

        $host = rtrim(idn_to_ascii($host, IDNA_DEFAULT, $variant), '.') . '.';

        return $this->validateDnsRecords($localPart, $host);
    }

    protected function validateDnsRecords(string $localPart, string $host): bool
    {
        // Get all MX, A and AAAA DNS records for host
        // Using @ as workaround to fix https://bugs.php.net/bug.php?id=73149
        $dnsRecords = @dns_get_record($host, DNS_MX + DNS_A + DNS_AAAA);


        // No MX, A or AAAA DNS records
        if (empty($dnsRecords)) {
            $this->error = new NoDNSRecord();
            return false;
        }

        // For each DNS record
        foreach ($dnsRecords as $dnsRecord) {
            if (!$this->validateMxRecord($localPart, $dnsRecord)) {
                return false;
            }
        }

        // No MX records (fallback to A or AAAA records)
        if (empty($this->mxRecords)) {
            $this->warnings[NoDNSMXRecord::CODE] = new NoDNSMXRecord();
        }

        return true;
    }

    protected function validateMxRecord(string $localPart, array $dnsRecord): bool
    {
        if ($dnsRecord['type'] !== 'MX') {
            return true;
        }

        // "Null MX" record indicates the domain accepts no mail (https://tools.ietf.org/html/rfc7505)
        $target = $dnsRecord['target'] ?? '';
        if ($target === '' || $target === '.') {
            $this->error = new DomainAcceptsNoMail();
            return false;
        }

        if ($this->xfBannedEmails)
        {
            /** @var \XF\Repository\Banning $banRepo */
            $banRepo = \XF::repository('XF:Banning');

            $email = $localPart . '@' . $target;

            if ($banRepo->isEmailBanned($email, $this->xfBannedEmails))
            {
                $this->warnings[static::BANNED_EMAIL_CODE] = true;
                return false;
            }
        }

        $this->mxRecords[] = $dnsRecord;

        return true;
    }
}