<?php

namespace SV\EmailDomainValidation\XF\Validator;

use SV\EmailDomainValidation\EmailValidation as XFEmailValidation;
use SV\EmailDomainValidation\Globals;
use SV\StandardLib\Helper;
use XF\Repository\Banning as BanningRepo;

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

    public function isValid($value, &$errorKey = null)
    {
        $isValid = parent::isValid($value, $errorKey);

        if ($value === '' || !$isValid)
        {
            if ($errorKey === 'banned' && $this->options['sv_extended_error'])
            {
                // extract *what* is banned
                $banningRepo = Helper::repository(BanningRepo::class);
                $entry = $banningRepo->getBannedEntryFromEmail($value, $this->options['banned']);
                $errorKey = ['banned_entry' => ['entry' => $entry]];
            }

            return $isValid;
        }

        if ($this->options['dns_validate'])
        {
            $emailValidation =  Helper::newExtendedClass(XFEmailValidation::class, $this->options['banned'] ?? []);
            if (!$emailValidation->isValid($value))
            {
                if ($this->options['sv_extended_error'])
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

                return false;
            }
        }

        return true;
    }
}