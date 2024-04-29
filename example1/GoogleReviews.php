<?php


use \api\modules\v1\models\Member;
use \api\modules\v1\models\Review;
use GuzzleHttp\Client;

class GoogleReviews implements \wowmi\ReviewServiceInterface, \wowmi\ReviewServiceUpdateInterface
{
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = \Yii::$app->params['company_reviews']['google']['access']['api_key'];
    }

    public function upload(Member $member)
    {
        if (isset($member->google_place_id)) {
            $result = $this->requestApi($member->google_place_id);
        }

        $db = \Yii::$app->db;
        $transaction = $db->beginTransaction();

        Review::deleteAll([
            'service' => self::SERVICE_GOOGLE,
            'member_id' => $member->id
        ]);

        if (isset($result['result']['reviews'])) {
            foreach ($result['result']['reviews'] as $apiReview) {
                $name = explode(' ', $apiReview["author_name"]);

                if ($apiReview["time"]) {
                    $date = (new \DateTime())
                        ->setTimestamp($apiReview["time"])
                        ->format("Y-m-d H:i:s");
                }

                $review = new Review();
                $review->member_id = $member->id;
                $review->service = self::SERVICE_GOOGLE;
                $review->rating = $apiReview["rating"];
                $review->first_name = $name[0] ?? null;
                $review->last_name = $name[1] ?? null;
                $review->avatar = $apiReview["profile_photo_url"];
                $review->date = $date ?? null;
                $review->total = $result["result"]["user_ratings_total"] ?? null;
                $review->reviews_page_url = $result["result"]["url"] ?? null;
                $review->content = $apiReview["text"] ?? null;
                $review->extra_data = json_encode($apiReview, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $review->save();
            }
        }

        $transaction->commit();
    }

    public function uploadAll()
    {
        $googleMembers = Member::find()
            ->where(['not', ['google_place_id' => null]])
            ->all();

        foreach ($googleMembers as $member){
            $this->upload($member);
        }
    }

    public function updateMember(Member $member, array $params): bool
    {
        if (!empty($params['place_id']) && $member->google_place_id != $params['place_id']) {
            $member->google_place_id = $params['place_id'];
            $member->save();

            return true;
        }

        return false;
    }

    private function requestApi(string $place_id): array
    {
        if (!$this->apiKey || !$place_id) {
            return [];
        }

        try {
            return json_decode(
                (new Client())
                    ->request("GET", "{$this->googleEndpoint}/json?place_id={$place_id}&key={$this->apiKey}")
                    ->getBody()
                    ->getContents(),
                true
            );
        } catch (\Throwable $exception) {
            return [];
        }
    }
}
