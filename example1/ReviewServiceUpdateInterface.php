<?php


use \api\modules\v1\models\Member;

interface ReviewServiceUpdateInterface
{
    function updateMember(Member $member, array $params): bool;
}
