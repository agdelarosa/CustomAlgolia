<?php

namespace Algolia\CustomAlgolia\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Pricing\Helper\Data;
use Magento\Catalog\Model\ProductRepository;

/**
 * Class TransformAttribute
 *
 * @author Alvin Glenn De La Rosa <alvinglenndelarosa@gmail.com>
 */
class TransformAttribute implements ObserverInterface
{
    private $storeManager;

    private $currencyFormatter;

    private $productRepository;

    public function __construct(
        StoreManagerInterface $storeManager,
        Data $currencyFormatter,
        ProductRepository $productRepository
    ) {
        $this->storeManager = $storeManager;
        $this->currencyFormatter = $currencyFormatter;
        $this->productRepository = $productRepository;
    }

    /**
     * @param Observer $observer
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute(Observer $observer)
    {
        $store = $this->storeManager->getStore();

        $currencyCode = $store->getCurrentCurrencyCode();

        $custom_data = $observer->getData('custom_data');

        $price = $custom_data['price'];

        $newPrice = ($this->getLowestProductPrice($custom_data['objectID'])) ?:  $price[$currencyCode]['default'];
        $price[$currencyCode]['default'] = $newPrice;
        $price[$currencyCode]['default_formated'] = $this->currencyFormatter->currency($newPrice, true, false);

        $custom_data['price'] = $price;

    }

    /**
     * Get Lowest Product Price
     *
     * @param $sku
     * @return float|int
     */
    public function getLowestProductPrice($id)
    {
        try {
            $product = $this->productRepository->getById($id);
            if($product->getTypeId() == \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE){
                $children = $product->getTypeInstance()->getUsedProducts($product);
                $price = [];
                foreach($children as $child) {
                    $price[] = $this->getTierPriceFromProduct($child); //$child->getPrice();
                }

            }
            $price[] = $this->getTierPriceFromProduct($product);

            return $this->getMinVal($price);

        } catch (\Magento\Framework\Exception\NoSuchEntityException $e){
            return 0;
        }
    }

    /**
     * Get lowest price from tier price.
     *
     * @param $product
     * @return float
     */
    protected function getTierPriceFromProduct($product)
    {
        $price = null;
        if ($product->getId()) {
            $price = ($product->getPrice() + $price > 0)
                ? $this->getMinVal([$product->getPrice(), $price])
                : 0.0;
            $tierPrices = $product->getTierPrice();
            if (count($tierPrices) > 0) {
                foreach ($tierPrices as $tierPrice) {
                    foreach (array_reverse($tierPrice) as $key => $value) {
                        if ($key == "price") {
                            $price = $this->getMinVal([$price, $value]);
                        }
                    }
                }
            }
            $minTierPrice = $price;
        } else {
            $minTierPrice = 0.0;
        }
        return $minTierPrice;
    }

    /**
     * Returns the minimum value of an array greater than zero.
     *
     * @param array $values
     * @return float
     */
    protected function getMinVal($values) {
        $values = array_diff(array_map('floatval', $values), [0]);
        if (!(is_array($values) && count($values))) {
            return false;
        }
        return min($values);
    }
}