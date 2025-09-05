<?php

namespace SV\EmailDomainValidation\XF\Validator;

use SV\EmailDomainValidation\EmailValidation as XFEmailValidation;
use SV\EmailDomainValidation\Globals;
use SV\StandardLib\Helper;
use XF\Repository\Banning as BanningRepo;
use function array_key_exists;
use function is_string;
use function mb_strtolower;
use function strlen;
use function strrpos;
use function substr;

/**
 * @Extends \XF\Validator\Email
 */
class Email extends XFCP_Email
{
    protected function setupOptionDefaults()
    {
        parent::setupOptionDefaults();
        $this->options['dns_validate'] = Globals::$doExtendedDomainValidation ?? true;
        $this->options['sv_extended_error'] = false;
    }

    /** @noinspection PhpMissingReturnTypeInspection */
    public function isValid($value, &$errorKey = null)
    {
        $options = $this->options;
        // move typo-detection *before* other checks (including banned email address check)
        $checkTypos = $options['check_typos'];
        if ($checkTypos && $value !== '')
        {
            $this->options['check_typos'] = false;
            if ($this->hasTypos($value, $errorKey))
            {
                return false;
            }
        }

        try
        {
            $isValid = parent::isValid($value, $errorKey);
        }
        finally
        {
            if ($checkTypos)
            {
                $this->options['check_typos'] = true;
            }
        }

        if ($value === '' || !$isValid)
        {
            if ($errorKey === 'banned' && ($options['sv_extended_error'] ?? false))
            {
                // extract *what* is banned
                $banningRepo = Helper::repository(BanningRepo::class);
                $entry = $banningRepo->getBannedEntryFromEmail($value, $options['banned']);
                $errorKey = ['banned_entry' => ['entry' => $entry]];
            }

            return $isValid;
        }

        if (($options['dns_validate'] ?? false) && $this->hasInvalidDNS($value, $errorKey))
        {
            return false;
        }

        return true;
    }

    protected function hasInvalidDNS(string $email, &$errorKey): bool
    {
        $options = $this->options;

        $emailValidation = Helper::newExtendedClass(XFEmailValidation::class, $options['banned']);
        if ($emailValidation->isValid($email))
        {
            return false;
        }

        if ($options['sv_extended_error'] ?? false)
        {
            $errorKey = [];
            $errorKey[] = $emailValidation->error;
            $bannedEntry = $emailValidation->warnings[XFEmailValidation::NO_DNS_MX_RECORD] ?? false;
            if (is_string($bannedEntry))
            {
                $errorKey[] = XFEmailValidation::NO_DNS_MX_RECORD;
            }
            $bannedEntry = $emailValidation->warnings[XFEmailValidation::BANNED_EMAIL] ?? false;
            if (is_string($bannedEntry))
            {
                $errorKey['banned_entry'] = ['entry' => $bannedEntry];
            }
        }
        else
        {
            /** @noinspection SpellCheckingInspection */
            $errorKey = ($emailValidation->warnings[XFEmailValidation::BANNED_EMAIL] ?? false)
                ? 'banned'
                : 'invaliddomain';
        }

        return true;
    }

    /** @noinspection SpellCheckingInspection */
    protected function getTldPermutations(): array
    {
        // should be all lowercase
        return [
            '.com' => [
                '.com.com',
                '.cmo',
                '.cm',
                '.co'
            ],
            '.co.fr' => [
                '.cofr',
                '.co.ft.com',
            ],
            '.co.br' => [
                '.couk',
                '.co.br.com',
            ],
            '.co.uk' => [
                '.couk',
                '.co.uk.com',
            ],
            '.co.jp' => [
                '.couk',
                '.co.jp.com',
            ],
        ];
    }

    /** @noinspection SpellCheckingInspection */
    protected function getDomainsPermutations(): array
    {
        // This is a very basic function and is really just trying to catch simple typos.
        // Most significantly, gamil.com since this can trigger an SFS action unexpectedly.
        return [
            'gmail' => [
                'gamil',
                'gmial',
                'gnail',
                'gmai',
                'gnai',
            ],
            'outlook' => [
                'outlok',
                'otlook',
                'oultook',
                'outloook',
                'outlo',
                'outloo',
                'ooutlook',
            ],
            'hotmail' => [
                'hotnail',
                'hotmai',
                'hotnai',
            ],
            'live' => [
                'livee',
                'liveee',
                'lvie',
                'liev',
                'windows',
                'window',
            ],
            'yahoo' => [
                'yaho',
                'yahooo',
                'yaaho',
                'yaahoo',
            ],
        ];
    }

    /** @var array<string,string>|null */
    protected static $misspelledDomains = null;

    protected function getBasicMisspelledDomains(): array
    {
        if (self::$misspelledDomains !== null)
        {
            return self::$misspelledDomains;
        }

        $misspelledDomains = [];
        $topLevelDomains = $this->getTldPermutations();
        $domains = $this->getDomainsPermutations();
        foreach ($domains as $domain => $domainPermutations)
        {
            foreach ($topLevelDomains as $tld => $tldPermutations)
            {
                $host = $domain . $tld;
                foreach ($domainPermutations as $domainPermutation)
                {
                    $misspelledDomains[$domainPermutation . $tld] = $host;
                }
                foreach ($tldPermutations as $tldPermutation)
                {
                    $misspelledDomains[$domain . $tldPermutation] = $host;
                    foreach ($domainPermutations as $domainPermutation)
                    {
                        $misspelledDomains[$domainPermutation . $tldPermutation] = $host;
                    }
                }
            }
        }

        self::$misspelledDomains = $misspelledDomains;

        return self::$misspelledDomains;
    }

    /**
     * @param string $email
     * @param array|string|null &$errorKey
     * @return bool
     */
    protected function hasTypos(string $email, &$errorKey): bool
    {
        $lastAtPos = strrpos($email, '@');
        if ($lastAtPos === false)
        {
            return false;
        }

        $domain = substr($email, $lastAtPos + 1);
        if ($domain === '')
        {
            return false;
        }

        $domain = mb_strtolower($domain);
        if ($domain[strlen($domain) - 1] === '.')
        {
            $domains = [$domain => substr($domain, 0, -1)];
        }
        else
        {
            $domains = $this->getBasicMisspelledDomains();
        }
        if (array_key_exists($domain, $domains))
        {
            $options = $this->options;
            if ($options['sv_extended_error'] ?? false)
            {
                $errorKey = ['typo_entry' => ['entry' => $domains[$domain]]];
            }
            else
            {
                $errorKey = 'typo';
            }

            return true;
        }

        return false;
    }
}