<?php
/**
 * FAQ Category Repository Interface
 *
 * @category  Panth
 * @package   Panth_Faq
 * @author    Panth
 * @copyright Copyright (c) 2025 Panth
 */
declare(strict_types=1);

namespace Panth\Faq\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Panth\Faq\Api\Data\CategoryInterface;

interface CategoryRepositoryInterface
{
    /**
     * Save category
     *
     * @param CategoryInterface $category
     * @return CategoryInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function save(CategoryInterface $category);

    /**
     * Retrieve category by ID
     *
     * @param int $categoryId
     * @return CategoryInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById($categoryId);

    /**
     * Retrieve categories matching the specified criteria
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return \Panth\Faq\Api\Data\CategorySearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria);

    /**
     * Delete category
     *
     * @param CategoryInterface $category
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function delete(CategoryInterface $category);

    /**
     * Delete category by ID
     *
     * @param int $categoryId
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function deleteById($categoryId);
}
