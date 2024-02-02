<?php

namespace SV\EmailDomainValidation\XF\Validator;

use SV\EmailDomainValidation\EmailValidation as XFEmailValidation;
use Egulias\EmailValidator\EmailValidator;
use SV\EmailDomainValidation\Globals;
use function assert;

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

            $class = \XF::extendClass(XFEmailValidation::class);
            $emailValidation = new $class($this->options['banned'] ?? []);
            assert($emailValidation instanceof XFEmailValidation);

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