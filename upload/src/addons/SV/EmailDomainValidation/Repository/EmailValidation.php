<?php

namespace SV\EmailDomainValidation\Repository;

use SV\SignupAbuseBlocking\Globals;
use SV\SignupAbuseBlocking\Spam\Checker\User\Email as EmailSpamCheck;
use SV\SignupAbuseBlocking\Spam\Checker\User\EmailDomain as EmailDomainSpamCheck;
use SV\StandardLib\Helper;
use XF\Entity\AdminNavigation as AdminNavigationEntity;
use XF\Entity\User as UserEntity;
use XF\Mvc\Entity\Repository;
use XF\Repository\User as UserRepo;
use XF\Spam\Checker\AbstractProvider;
use XF\Spam\UserChecker;
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

    protected function getEmailSpamCheckersForSignup(): array
    {
        if (Helper::isAddOnActive('SV/SignupAbuseBlocking'))
        {
            return [
                EmailSpamCheck::class,
                EmailDomainSpamCheck::class,
            ];
        }

        return [];
    }

    protected function checkEmailForSignup(string $email, array &$errors): void
    {
        $signupCheckers = $this->getEmailSpamCheckersForSignup();

        if (count($signupCheckers) === 0)
        {
            return;
        }

        $app = \XF::app();
        $guestUser = Helper::repository(UserRepo::class)->getGuestUser(null, function (array $data) use ($email) {
            $data['email'] = $email;

            return $data;
        });

        foreach ($signupCheckers as $class)
        {
            if (class_exists($class))
            {
                $checker = Helper::newExtendedClass(UserChecker::class, $app);
                $provider = Helper::newExtendedClass($class, $checker, $app);

                $this->doCheckEmailForSignup($provider, $guestUser);

                foreach ($checker->getDetails() as $row)
                {
                    $errors[] = \XF::phrase($row['phrase'], $row['data']);
                }
            }
        }
    }

    protected function doCheckEmailForSignup(AbstractProvider $provider, UserEntity $guestUser): void
    {
        $provider->check($guestUser);
    }

    public function validateEmailAddress(string $email, string &$formatedEmail, array &$signupErrors, array &$errors, array &$warnings): bool
    {
        $signupErrors = $errors = $warnings = [];

        $app = \XF::app();
        $emailValidator = $app->validator(EmailValidator::class);
        $emailValidator->setOption('banned', $app->container('bannedEmails'));
        $emailValidator->setOption('check_typos', true);
        $emailValidator->setOption('allow_local', false);
        $emailValidator->setOption('dns_validate', true);
        $emailValidator->setOption('sv_extended_error', true);
        $formatedEmail = $emailValidator->coerceValue($email);

        $this->checkEmailForSignup($formatedEmail, $signupErrors);

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