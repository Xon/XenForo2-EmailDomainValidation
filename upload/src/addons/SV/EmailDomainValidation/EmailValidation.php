<?php
/**
 * @noinspection PhpComposerExtensionStubsInspection
 */

namespace SV\EmailDomainValidation;

use SV\StandardLib\Helper;
use function count;
use function dns_get_record;
use function explode;
use function in_array;
use function mb_strtolower;
use function rtrim;
use function strrpos;
use function substr;

/**
 * Based off Egulias\EmailValidator\Validation\DNSCheckValidation but stripped of functionality which isn't needed
 */
class EmailValidation
{
    public const BANNED_EMAIL             = 'banned_email';
    public const LOCAL_OR_RESERVED_DOMAIN = 'local_or_reserved_domain';
    public const NO_DNS_RECORD            = 'no_dns_record';
    public const NO_DNS_MX_RECORD         = 'no_dns_mx_record';
    public const ACCEPTS_NO_MAIL          = 'accepts_no_mail';


    /** @var string */
    public $error;
    /** @var array<string, bool|string|array> */
    public $warnings;

    /**
     * @var array<array>
     */
    protected $mxRecords = [];

    protected $xfBannedEmails;


    public function __construct($xfBannedEmails)
    {
        $this->xfBannedEmails = $xfBannedEmails;
    }

    public function isValid(string $email): bool
    {
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
        if ($isLocalDomain || $isReservedTopLevel)
        {
            $this->error = static::LOCAL_OR_RESERVED_DOMAIN;

            return false;
        }

        return $this->checkDns($localPart, $host);
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
        if (empty($dnsRecords))
        {
            $this->error = static::NO_DNS_RECORD;

            return false;
        }

        // For each DNS record
        foreach ($dnsRecords as $dnsRecord)
        {
            if (!$this->validateMxRecord($localPart, $dnsRecord))
            {
                return false;
            }
        }

        // No MX records (fallback to A or AAAA records)
        if (empty($this->mxRecords))
        {
            $this->warnings[static::NO_DNS_MX_RECORD] = true;
        }

        return true;
    }

    protected function validateMxRecord(string $localPart, array $dnsRecord): bool
    {
        if ($dnsRecord['type'] !== 'MX')
        {
            return true;
        }

        // "Null MX" record indicates the domain accepts no mail (https://tools.ietf.org/html/rfc7505)
        $target = $dnsRecord['target'] ?? '';
        if ($target === '' || $target === '.')
        {
            $this->error = static::ACCEPTS_NO_MAIL;

            return false;
        }

        if ($this->xfBannedEmails)
        {
            $banRepo = Helper::repository(\XF\Repository\Banning::class);

            $email = $localPart . '@' . $target;

            $bannedEntry = $banRepo->getBannedEntryFromEmail($email, $this->xfBannedEmails);
            if ($bannedEntry !== null)
            {
                $this->warnings[static::BANNED_EMAIL] = $bannedEntry;

                return false;
            }
        }

        $this->mxRecords[] = $dnsRecord;

        return true;
    }
}