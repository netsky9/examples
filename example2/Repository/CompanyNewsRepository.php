<?php

namespace App\Repository;

use App\Entity\CompanyNews;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CompanyNews>
 *
 * @method CompanyNews|null find($id, $lockMode = null, $lockVersion = null)
 * @method CompanyNews|null findOneBy(array $criteria, array $orderBy = null)
 * @method CompanyNews[]    findAll()
 * @method CompanyNews[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CompanyNewsRepository extends ServiceEntityRepository
{
    private const COUNT_LAST_NEWS = 7;

    public function __construct(
        ManagerRegistry $registry,
        readonly AddressRepository $addressRepository,
        readonly ReviewRepository $reviewRepository,
        readonly RubricateCompanyRepository $rubricateCompanyRepository
    )
    {
        parent::__construct($registry, CompanyNews::class);
    }

    public function getLastCompanyNews(): array
    {
        $companyNewsItems = $this->createQueryBuilder('cn')
            ->select("cn")
            ->where('cn.deletedAt IS NULL')
            ->leftJoin('cn.company', 'cnc')
            ->addSelect('cnc')
            ->orderBy('cn.id')
            ->setMaxResults(self::COUNT_LAST_NEWS)
            ->getQuery()
            ->getResult();

        $result = [];

        foreach ($companyNewsItems as $companyNews) {
            $rubricateCompanies = $this->rubricateCompanyRepository
                ->getAllCategoriesByCompanyId(
                    $companyNews->getCompany()->getId()
                );
            $addresses = $this->addressRepository->getCompanyAddress($companyNews->getCompany()->getId());

            $result[] = [
                'companyNews' => $companyNews,
                'companyAddresses' => $addresses,
                'companyRubricates' => $rubricateCompanies,
            ];
        }

        return $result;
    }

    public function getAllCompanyNewsQuery()
    {
        return $this->createQueryBuilder('cn')
            ->select("cn")
            ->where('cn.deletedAt IS NULL')
            ->leftJoin('cn.company', 'cnc')
            ->addSelect('cnc')
            ->getQuery();
    }

}
