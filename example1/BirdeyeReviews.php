<?php


use \api\modules\v1\models\Member;
use \api\modules\v1\models\Review;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Utils;

class BirdeyeReviews implements \wowmi\ReviewServiceInterface, \wowmi\ReviewServiceUpdateInterface
{
    private string $apiKey;
    private int $limit;

    public function __construct()
    {
        $this->apiKey = \Yii::$app->params['company_reviews']['birdeye']['access']['api_key'];
        $this->limit = \Yii::$app->params['company_reviews']['birdeye']['limit'];
    }

    public function upload(Member $member)
    {
        if (isset($member->birdeye_business_id)) {
            $result = $this->requestApi($member->birdeye_business_id);
        }

        $db = \Yii::$app->db;
        $transaction = $db->beginTransaction();

        Review::deleteAll([
            'service' => self::SERVICE_BIRDEYE,
            'member_id' => $member->id
        ]);

        if (isset($result)) {
            foreach ($result as $apiReview) {
                if (!isset($apiReview["comments"]) || empty($apiReview["comments"])) {
                    continue;
                }

                if (isset($apiReview["rdate"])) {
                    $date = (new \DateTime())
                        ->setTimestamp($apiReview["rdate"] / 1000)
                        ->format("Y-m-d H:i:s");
                }

                $review = new Review();
                $review->member_id = $member->id;
                $review->service = self::SERVICE_BIRDEYE;
                $review->rating = $apiReview["rating"];
                $review->first_name = $apiReview['reviewer']['firstName'] ?? null;
                $review->last_name = $apiReview['reviewer']['lastName'] ?? null;
                $review->avatar = $apiReview["reviewer"]["thumbnailUrl"];
                $review->date = $date ?? null;
                $review->total = null;
                $review->reviews_page_url = $apiReview["uniqueReviewUrl"] ?? null;
                $review->content = $apiReview["comments"] ?? null;
                $review->extra_data = json_encode($apiReview, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $review->save();
            }
        }

        $transaction->commit();
    }

    public function uploadAll()
    {
        $birdeyeMembers = Member::find()
            ->where(['not', ['birdeye_business_id' => null]])
            ->all();

        foreach ($birdeyeMembers as $member) {
            $this->upload($member);
        }
    }

    public function updateMember(Member $member, array $params): bool
    {
        if (!empty($params['business_id']) && $member->birdeye_business_id != $params['business_id']) {
            $member->birdeye_business_id = $params['business_id'];
            $member->save();

            return true;
        }

        return false;
    }

    private function requestApi(string $business_id): array
    {
        if (!$this->apiKey || !$business_id) {
            return [];
        }

        $url = "{$this->birdEyeEndpoint}/{$business_id}?sindex=0&count={$this->limit}&api_key={$this->apiKey}";

        try {
            $client = new Client();

            $response = $client->post($url, [
                'headers' => [
                    'Content-Type'  => 'application/json',
                ],
                'json' => (object) []
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (\Throwable $exception) {
            return [];
        }
    }
}
