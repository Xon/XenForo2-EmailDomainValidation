<?php

namespace SV\EmailDomainValidation;

use SV\EmailDomainValidation\Repository\EmailValidation as EmailValidationRepo;
use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;

class Setup extends AbstractSetup
{
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    public function postInstall(array &$stateChanges): void
    {
        parent::postInstall($stateChanges);
        EmailValidationRepo::get()->shimAdminNavigation();
    }

    public function postUpgrade($previousVersion, array &$stateChanges): void
    {
        $previousVersion = (int)$previousVersion;
        parent::postUpgrade($previousVersion, $stateChanges);
        EmailValidationRepo::get()->shimAdminNavigation();
    }

    public function postRebuild(): void
    {
        parent::postRebuild();
        EmailValidationRepo::get()->shimAdminNavigation();
    }
}