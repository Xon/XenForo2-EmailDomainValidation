<?php

namespace SV\EmailDomainValidation\Repository;

use SV\StandardLib\Helper;
use XF\Entity\AdminNavigation as AdminNavigationEntity;
use XF\Mvc\Entity\Repository;
use XF\Validator\Email as EmailValidator;

class EmailValidation extends Repository
{
    public static function get(): self
    {
        return Helper::repository(self::class);
    }

    public function canDoChecksAndTests(): bool
    {
        if (\XF::$versionId < 2030470)
        {
            return true;
        }

        return \XF::visitor()->hasAdminPermission('checksAndTests');
    }

    public function shimAdminNavigation(): void
    {
        /** @var AdminNavigationEntity|null $redisInfo */
        $redisInfo = \XF::app()->find('XF:AdminNavigation', 'svValidateEmail');
        if ($redisInfo === null)
        {
            return;
        }

        $redisInfo->admin_permission_id = \XF::$versionId >= 2030470 ? 'checksAndTests' : '';
        $redisInfo->saveIfChanged();
    }

    public function validateEmailAddress(string $email, string &$formatedEmail, array &$signupErrors, array &$errors, array &$warnings): bool
    {
        $signupErrors = $errors = $warnings = [];

        $app = \XF::app();
        $emailValidator = $app->validator(EmailValidator::class);
        $emailValidator->setOption('banned', $app->container('bannedEmails'));
        $emailValidator->setOption('check_typos', true);
        $emailValidator->setOption('check_typos', true);
        $emailValidator->setOption('dns_validate', true);
        $emailValidator->setOption('sv_extended_error', true);
        $formatedEmail = $emailValidator->coerceValue($email);
        /** @var array<string,string>|string|null $errorKey */
        $valid = $emailValidator->isValid($formatedEmail, $errorKey);
        if ($valid)
        {
            return true;
        }
        if (!is_array($errorKey))
        {
            if ($errorKey === null)
            {
                $errorKey = 'invalid';
            }
            $errorKeys = [$errorKey];
        }
        else
        {
            $errorKeys = $errorKey;
        }

        if (!array_key_exists('invalid', $errorKeys))
        {
            array_unshift($errorKeys, 'invalid');
        }

        foreach ($errorKeys as $key => $data)
        {
            if (is_int($key))
            {
                $key = $data;
                $data = [];
            }

            $errors[$key] = \XF::phrase('svEmailDomainValidation_email.' . $key, $data);
        }

        return false;
    }
}