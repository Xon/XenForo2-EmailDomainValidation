<?php

namespace SV\EmailDomainValidation\XF\Entity;

use SV\EmailDomainValidation\Globals;

/**
 * @Extends \XF\Entity\User
 */
class User extends XFCP_User
{
    /** @noinspection PhpMissingReturnTypeInspection */
    protected function verifyEmail(&$email)
    {
        $oldValue = Globals::$doExtendedDomainValidation;
        if ($this->getOption('admin_edit'))
        {
            Globals::$doExtendedDomainValidation = !(\XF::options()->svAdminBypassEmailDnsChecks ?? false);
        }
        try
        {
            return parent::verifyEmail($email);
        }
        finally
        {
            Globals::$doExtendedDomainValidation = $oldValue;
        }
    }
}