<?php


use \api\modules\v1\models\Member;
use \api\modules\v1\models\Review;
use GuzzleHttp\Client;

class ExperienceReviews implements \wowmi\ReviewServiceInterface
{
    private array $access;
    private int $limit;

    public function __construct()
    {
        $this->access = \Yii::$app->params['company_reviews']['experience']['access'];
        $this->limit = \Yii::$app->params['company_reviews']['experience']['limit'];
    }

    public function upload(Member $member)
    {
        $companyReviews = \Yii::$app->params['company_reviews'];
        $reviews = [];

        if (!empty($member->company) && isset($companyReviews['experience']['company'][$member->company])) {
            $accountId = $companyReviews['experience']['company'][$member->company]['account_id'];
        }

        if (isset($accountId)) {
            // Default API
            $requestReviews = $this->requestDefault($accountId, $member->agent_email);

            if (isset($requestReviews["data"]["surveys"])) {
                foreach ($requestReviews["data"]["surveys"] as $rawReview) {
                    if ($rawReview["review"]["is_verified"]) {
                        $reviews[] = [
                            'member_id' => $member->id,
                            'review_id' => $rawReview["id"],
                            'rating' => $rawReview["review"]["rating"],
                            'avg_rating' => $requestReviews["data"]["overview"]['avg_score'],
                            'first_name' => $rawReview["transactionInfo"]["customer_first_name"],
                            'last_name' => $rawReview["transactionInfo"]["customer_last_name"] ?? null,
                            'state' => $rawReview["transactionInfo"]["state"],
                            'city' => $rawReview["transactionInfo"]["city"],
                            'updated_at' => $rawReview["review"]["updated_at"],
                            'total' => $requestReviews["data"]["overview"]["total_review_count"] ?? null,
                            'content' => $rawReview["review"]["review"] ?? null,
                            'reviews_page_url' => $rawReview["serviceProviderInfo"]["agent_profile_url"] ?? null,
                            'reviewData' => $rawReview
                        ];
                    }
                }
            }
        } else {
            // Widget API
            $requestReviews = $this->requestWidget($member->agent_email);

            if (isset($requestReviews["survey_reviews"]["reviews"])) {
                foreach ($requestReviews["survey_reviews"]["reviews"] as $rawReview) {
                    if ($rawReview["is_verified"]) {
                        $reviews[] = [
                            'member_id' => $member->id,
                            'review_id' => $rawReview["id"],
                            'rating' => $rawReview["rating"],
                            'avg_rating' => $requestReviews["survey_reviews"]["average_score"],
                            'first_name' => $rawReview["customer_first_name"],
                            'last_name' => $rawReview["customer_last_name"] ?? null,
                            'state' => $rawReview["state"],
                            'city' => $rawReview["city"],
                            'updated_at' => $rawReview["updated_at"],
                            'total' => $requestReviews["survey_reviews"]["count"] ?? null,
                            'content' => $rawReview["review"] ?? null,
                            'reviewData' => $rawReview
                        ];
                    }
                }
            }
        }

        $db = \Yii::$app->db;
        $transaction = $db->beginTransaction();

        Review::deleteAll([
            'service' => self::SERVICE_EXPERIENCE,
            'member_id' => $member->id
        ]);

        foreach ($reviews as $review) {
            $this->uploadReview($review);
        }

        $transaction->commit();
    }

    public function uploadAll()
    {
        $experienceMembers = Member::find()
            ->where(['not', ['agent_email' => null]])
            ->all();

        foreach ($experienceMembers as $member){
            $this->upload($member);
        }
    }

    private function uploadReview(array $reviewData)
    {
        if ($reviewData['updated_at']) {
            $date = (new \DateTime())
                ->setTimestamp(strtotime($reviewData['updated_at']))
                ->format("Y-m-d H:i:s");
        }

        $review = new Review();
        $review->member_id = $reviewData['member_id'];
        $review->service = self::SERVICE_EXPERIENCE;
        $review->review_id = $reviewData['review_id'];
        $review->rating = $reviewData['rating'];
        $review->avg_rating = $reviewData['avg_rating'];
        $review->first_name = $reviewData['first_name'];
        $review->last_name = $reviewData['last_name'];
        $review->reviews_page_url = $reviewData['reviews_page_url'] ?? null;
        $review->country = 'US';
        $review->state = $reviewData['state'];
        $review->city = $reviewData['city'];
        $review->date = $date ?? null;
        $review->total = $reviewData['total'];
        $review->content = $reviewData['content'];
        $review->extra_data = json_encode($reviewData['reviewData'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $review->save();
    }

    private function requestApi(string $url, array $query, string $method = 'GET'): array
    {
        try {
            $client = new Client();
            $response = $client->request($method, $url, ["query" => $query]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (\Throwable $exception) {
            return [];
        }
    }

    private function requestWidget(string $agent_email, string $order = 'desc'): array
    {
        return $this->requestApi($this->access['widget']['url'], [
            "widget_key" => $this->access['widget']['widget_key'],
            "api_key" => $this->access['widget']['api_key'],
            "agent_email" => $agent_email,
            "limit" => $this->limit,
            "order" => $order
        ]);
    }

    private function requestDefault(int $account_id, string $agent_email, string $order = 'desc', float $rating_min = 4.5): array
    {
        return $this->requestApi($this->access['default']['url'], [
            "app_id" => $this->access['default']['app_id'],
            "app_secret" => $this->access['default']['app_secret'],
            "account_id" => $account_id,
            "agent_email" => $agent_email,
            "limit" => $this->limit,
            "order" => $order,
            "rating_min" => $rating_min,
        ]);
    }
}
