<?php

namespace SV\EmailDomainValidation\XF\Validator;

use SV\EmailDomainValidation\EmailValidation as XFEmailValidation;
use Egulias\EmailValidator\EmailValidator;
use SV\EmailDomainValidation\Globals;
use SV\StandardLib\Helper;

/**
 * @Extends \XF\Validator\Email
 */
class Email extends XFCP_Email
{
    protected function setupOptionDefaults()
    {
        parent::setupOptionDefaults();
        $this->options['dns_validate'] = Globals::$doExtendedDomainValidation ?? true;
    }

    public function isValid($value, &$errorKey = null)
    {
        $isValid = parent::isValid($value, $errorKory);

        if ($isValid && $value !== '' && ($this->options['dns_validate'] ?? true))
        {
            $validator = new EmailValidator();

            $emailValidation =  Helper::newExtendedClass(XFEmailValidation::class, $this->options['banned'] ?? []);
            if (!$validator->isValid($value, $emailValidation))
            {
                $errorKey = $validator->getWarnings()[XFEmailValidation::BANNED_EMAIL_CODE] ?? false
                    ? 'banned'
                    : 'invaliddomain';

                return false;
            }
        }

        return $isValid;
    }
}