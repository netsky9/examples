<?php

namespace App\Service;

use App\Entity\CompanyNews;
use App\Repository\CompanyNewsRepository;
use App\Repository\RubricateRepository;
use Knp\Bundle\PaginatorBundle\Pagination\SlidingPagination;
use Knp\Component\Pager\PaginatorInterface;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;
use Vich\UploaderBundle\Templating\Helper\UploaderHelperInterface;
use Liip\ImagineBundle\Service\FilterService;


class NewsService
{
    public const COUNT_NEWS_PER_PAGE = 9;

    public function __construct(
        readonly SerializerInterface $serializer,
        readonly CompanyNewsRepository $companyNewsRepository,
        readonly UploaderHelper $uploaderHelper,
        readonly CacheManager $cacheManager,
        readonly UrlGeneratorInterface $urlGenerator,
        readonly CacheService $cacheService,
        readonly CategoryCompanyService $categoryCompanyService
    ) {
    }

    public function getLastCompanyNews()
    {
        $cache = $this->cacheService->setKey(CacheService::CACHE_NEWS_LAST);

        if ($cache->isEmpty()) {
            $items = $this->companyNewsRepository->getLastCompanyNews();
            $result = [];

            foreach ($items as $item) {
                $result[] = [
                    'title' => $item['companyNews']->getTitle(),
                    'link' => $this->categoryCompanyService->generateLink(
                        $item['companyAddresses'][0]->getRegion()->getAlias() ?? '',
                        $item['companyRubricates'][0]->getRubricate()->getAlias() ?? '',
                        $item['companyNews']->getCompany()->getAlias() .'-'.$item['companyNews']->getCompany()->getId()
                    ),
                    'shortText' => $this->createPreviewText($item['companyNews']->getText()),
                    'imageCover' => $this->uploaderHelper->asset($item['companyNews'], 'imageCover'),
                    'company' => [
                        'name' => $item['companyNews']->getCompany()->getName(),
                        'address' => $item['companyAddresses'][0]->getRegion()->getName() . ' ' . $item['companyAddresses'][0]->getName(),
                        'rating' => $item['companyNews']->getCompany()->getRating()
                    ]
                ];
            }

            $cache->setJson($result);
        } else {
            $result = $cache->getJson();
        }

        return $result;
    }

    public function getPaginateCompanyNews(int $page, SlidingPagination $pagination)
    {
        $paginationItems = [];

        $cache = $this->cacheService->setKey(CacheService::CACHE_NEWS_PAGINATE . '.' . $page);

        if ($cache->isEmpty()) {
            foreach ($pagination->getItems() as $companyNews) {
                $regionAlias = '';
                $address = '';
                $categoryAlias = '';
                $firstAddress = $companyNews->getCompany()->getAddresses()[0] ?? null;
                $companyRubricates = $companyNews->getCompany()->getRubricateCompanies()[0] ?? null;

                if ($firstAddress) {
                    $regionAlias = $firstAddress->getRegion()->getAlias() ?? '';
                    $address = $firstAddress->getRegion()->getName() . ' ' . $firstAddress->getName();
                }

                if ($companyRubricates) {
                    $categoryAlias = $companyRubricates->getRubricate()->getAlias() ?? '';
                }

                $paginationItems[] = [
                    'title' => $companyNews->getTitle(),
                    'link' => $this->categoryCompanyService->generateLink(
                        $regionAlias,
                        $categoryAlias,
                        $companyNews->getCompany()->getAlias() . '-' . $companyNews->getCompany()->getId()
                    ),
                    'shortText' => $this->createPreviewText($companyNews->getText()),
                    'imageCover' => $this->cacheManager->getBrowserPath(
                        $this->uploaderHelper->asset($companyNews, 'imageCover'),
                        'medium'
                    ),
                    'company' => [
                        'name' => $companyNews->getCompany()->getName(),
                        'address' => $address,
                        'rating' => $companyNews->getCompany()->getRating()
                    ]
                ];
            }

            $cache->setJson($paginationItems);
        } else {
            $paginationItems = $cache->getJson();
        }

        return $paginationItems;
    }

    private function createPreviewText(string $text)
    {
        $result = trim(preg_replace('!\s+!', ' ', strip_tags(substr($text, 0, 100))));
        $result = str_replace("&nbsp;", " ", $result);
        $result = rtrim($result, '!.,; ');

        return $result;
    }
}
