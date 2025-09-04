<?php

namespace SV\EmailDomainValidation\XF\Admin\Controller;

use SV\EmailDomainValidation\Repository\EmailValidation;
use XF\Mvc\Reply\AbstractReply;

/**
 * @extends \XF\Admin\Controller\Tools
 */
class Tools extends XFCP_Tools
{
    public function actionTestEmailAddressValidity(): ?AbstractReply
    {
        $repo = EmailValidation::get();

        $this->setSectionContext('svTestEmailAddressValidity');
        if (!$repo->canDoChecksAndTests())
        {
            return $this->noPermission();
        }

        $formatedEmail = $email = (string)$this->filter('email', 'str');
        $valid = false;
        $signupErrors = $errors = $warnings = [];
        if ($email !== '')
        {
            $valid = $repo->validateEmailAddress($email, $formatedEmail, $signupErrors, $errors, $warnings);
        }

        $viewParams = [
            'email' => $email,
            'formatedEmail' => $formatedEmail,
            'isValid' => $valid,
            'signupErrors' => $signupErrors,
            'errors' => $errors,
            'warnings' => $warnings,
        ];

        return $this->view('', 'svEmailDomainValidation_tools_test_email', $viewParams);
    }
}