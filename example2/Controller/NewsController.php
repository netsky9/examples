<?php

namespace App\Controller\Frontend;

use App\Repository\CompanyDiscountRepository;
use App\Repository\CompanyNewsRepository;
use App\Repository\RegionRepository;
use App\Service\NewsService;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class NewsController extends BaseController
{
    public function __construct(
        readonly CompanyNewsRepository $companyNewsRepository,
        readonly RegionRepository $regionRepository,
        readonly NewsService $newsService
    ) {
        parent::__construct($regionRepository);
    }

    #[Route('/news', name: 'app_frontend_news', priority: 1)]
    public function index(Request $request, PaginatorInterface $paginator): Response
    {
        $page = $request->query->getInt('page', 1);

        $pagination = $paginator->paginate(
            $this->companyNewsRepository->getAllCompanyNewsQuery(),
            $page,
            NewsService::COUNT_NEWS_PER_PAGE
        );

        $paginationItems = $this->newsService->getPaginateCompanyNews($page, $pagination);

        return $this->render('frontend/news/index.html.twig', compact(
            'pagination',
            'paginationItems'
        ));
    }
}
