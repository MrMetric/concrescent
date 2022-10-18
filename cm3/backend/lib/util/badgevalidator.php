<?php

namespace CM3_Lib\util;

use Respect\Validation\Validator as v;
use CM3_Lib\database\TableValidator;
use CM3_Lib\database\View;
use CM3_Lib\database\Join;
use CM3_Lib\database\SearchTerm;

use CM3_Lib\models\attendee\badgetype as a_badge_type;
use CM3_Lib\models\application\badgetype as g_badge_type;
use CM3_Lib\models\staff\badgetype as s_badge_type;
use CM3_Lib\models\attendee\badge as a_badge;
use CM3_Lib\models\application\submission as g_badge_submission;
use CM3_Lib\models\application\submissionapplicant as g_badge;
use CM3_Lib\models\application\group as g_group;
use CM3_Lib\models\staff\badge as s_badge;
use CM3_Lib\models\attendee\addon as a_addon;
use CM3_Lib\models\attendee\addonmap as a_addonmap;

use CM3_Lib\util\CurrentUserInfo;

final class badgevalidator
{
    public function __construct(
        private a_badge_type $a_badge_type,
        private g_badge_type $g_badge_type,
        private s_badge_type $s_badge_type,
        private a_badge $a_badge,
        private g_badge $g_badge,
        private s_badge $s_badge,
        private g_group $g_group,
        private a_addon $a_addon,
        private a_addonmap $a_addonmap,
        private g_badge_submission $g_badge_submission,
        private badgepromoapplicator $badgepromoapplicator,
        private CurrentUserInfo $CurrentUserInfo
    ) {
    }

    //Returns an array of the errors in this badge
    public function ValdateCartBadge(&$item)
    {
        $result = array();
        switch ($item['context_code'] ?? 'A') {
            case 'A':
                //Init the validator
                $v = new TableValidator($this->a_badge);
                //Add the badge-type validations
                if (!empty($item['badge_type_id'])) {
                    $badgeType = $this->a_badge_type->GetByID($item['badge_type_id'], $this->getBadgeTypeView());
                    if ($badgeType !== false) {
                        $this->AddBadgeValidations($v, $badgeType, $item);
                    }
                }


                //Special: Also apply the current promo if specified
                if (isset($item['payment_promo_code'])) {
                    $this->badgepromoapplicator->TryApplyCode($item, $item['payment_promo_code']);
                }
                break;
            case 'S':
                //Init the validator
                $v = new TableValidator($this->s_badge);
                //Add the badge-type validations
                if (!empty($item['badge_type_id'])) {
                    $badgeType = $this->s_badge_type->GetByID($item['badge_type_id'], $this->getBadgeTypeView());
                    if ($badgeType !== false) {
                        $this->AddBadgeValidations($v, $badgeType, $item);
                    }
                }
                break;
            default: //Assume it's an application
                //Init the validator
                $v = new TableValidator($this->g_badge_submission);
                //Add the badge-type validations
                if (!empty($item['badge_type_id'])) {
                    $badgeType = $this->g_badge_type->GetByID($item['badge_type_id'], $this->getBadgeTypeView());
                    if ($badgeType !== false) {
                        $this->AddBadgeValidations($v, $badgeType, $item);
                    }
                }
                //TODO: Add submission applicants
        }

        //Add the questions validator
        //TODO: Implement

        $v->Validate($item);
        return $v->GetErrors();
    }

    private function getBadgeTypeView($groupApp = false)
    {
        return new View(array(
            'start_date',
            'end_date',
            'min_age',
            'max_age',
            $groupApp ? 'price_per_applicant' : 'price'
        ));
    }

    private function AddBadgeValidations(TableValidator &$v, array $badgetypeData, &$item)
    {
        //TODO: Test for things like badge upgrades for the start_date and end_date?

        //TODO: This isn't right, need to advance the year in accordance to the event date and today....
        $bday = new v();
        if (!empty($badgetypeData['min_age'])) {
            $bday = $bday->MinAge($badgetypeData['min_age']);
        }
        if (!empty($badgetypeData['max_age'])) {
            $bday = $bday->MaxAge($badgetypeData['max_age']);
        }
        $v->addColumnValidator('date_of_birth', $bday);

        $v->addColumnValidator('notify_email', v::Optional(v::Email()), true);
        $v->addColumnValidator('ice_email_address', v::Optional(v::Email()), true);
        //Add in some details from the badge_type
        if (isset($badgetypeData['price'])) {
            $item['payment_badge_price'] = $badgetypeData['price'];
        }
        if (isset($badgetypeData['price_per_applicant'])) {
            $item['payment_badge_price'] = $badgetypeData['price_per_applicant'];
        }

        //Addons validation
        if (isset($item['addons'])) {
            $availableaddons = array_column($this->a_addon->Search(
                new View(
                    array(
                        'id',
                        'price',
                    ),
                    array(
                       new Join(
                           $this->a_addonmap,
                           array(
                             'addon_id' => 'id',
                             new SearchTerm('badge_type_id', $item['badge_type_id'])
                           ),
                           alias:'map'
                       )
                   )
                ),
                array(
                    new SearchTerm('active', 1),
                    $this->CurrentUserInfo->EventIdSearchTerm()
                )
            ), null, 'id');

            $v->addColumnValidator('addons', v::ArrayVal()->key('addon_id', v::in(array_keys($availableaddons)), false), true);
            foreach ($item['addons'] as &$addon) {
                if (!isset($availableaddons[$addon['addon_id'] ?? null])) {
                    //Wat this should have been verified?
                    $addon['err'] = 'Not found this addon?';
                    continue;
                }
                $faddon = $availableaddons[$addon['addon_id']];
                $addon['payment_price'] = $faddon['price'];
            }
        }
    }
}
