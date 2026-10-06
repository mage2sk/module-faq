<?php
/**
 * FAQ Item Repository Interface
 *
 * @category  Panth
 * @package   Panth_Faq
 * @author    Panth
 * @copyright Copyright (c) 2025 Panth
 */
declare(strict_types=1);

namespace Panth\Faq\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Panth\Faq\Api\Data\ItemInterface;

interface ItemRepositoryInterface
{
    /**
     * Save item
     *
     * @param ItemInterface $item
     * @return ItemInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function save(ItemInterface $item);

    /**
     * Retrieve item by ID
     *
     * @param int $itemId
     * @return ItemInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById($itemId);

    /**
     * Retrieve items matching the specified criteria
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return \Panth\Faq\Api\Data\ItemSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria);

    /**
     * Delete item
     *
     * @param ItemInterface $item
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function delete(ItemInterface $item);

    /**
     * Delete item by ID
     *
     * @param int $itemId
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function deleteById($itemId);
}
