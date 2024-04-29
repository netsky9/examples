<?php


use \api\modules\v1\models\Member;

interface ReviewServiceInterface
{
    const SERVICE_GOOGLE = 'google';
    const SERVICE_EXPERIENCE = 'experience';
    const SERVICE_ZILLOW = 'zillow';
    const SERVICE_BIRDEYE = 'birdeye';

    function upload(Member $member);

    function uploadAll();
}
