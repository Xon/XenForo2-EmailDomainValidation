<?php

namespace SV\EmailDomainValidation\XF\Validator;

use SV\EmailDomainValidation\EmailValidation as XFEmailValidation;
use SV\EmailDomainValidation\Globals;
use SV\StandardLib\Helper;
use XF\Repository\Banning as BanningRepo;
use function is_string;

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
        $isValid = parent::isValid($value, $errorKey);
        $options = $this->options;

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

    /**
     * @param string $email
     * @param array|string|null &$errorKey
     * @return bool
     */
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
            $errorKey = ($emailValidation->warnings[XFEmailValidation::BANNED_EMAIL] ?? false)
                ? 'banned'
                : 'invaliddomain';
        }

        return true;
    }
}