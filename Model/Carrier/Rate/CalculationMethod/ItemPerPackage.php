<?php
declare(strict_types=1);

namespace DmiRud\ShipStation\Model\Carrier\Rate\CalculationMethod;

use DmiRud\ShipStation\Exception\NoPackageCreatedForService;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote\Address\Item;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Rate\PackageResult;
use Magento\Shipping\Model\Rate\Result as RateResult;
use DmiRud\ShipStation\Exception\NoServiceFoundForProduct;
use DmiRud\ShipStation\Model\Api\RequestInterface;
use DmiRud\ShipStation\Model\Carrier;
use DmiRud\ShipStation\Model\Carrier\Rate\CalculationMethodAbstract;

class ItemPerPackage extends CalculationMethodAbstract
{
    public const METHOD_CODE = 'item_per_package';

    /**
     * Prepares ShipStation API request models and its payload
     *
     * @param RateRequest $rateRequest
     * @param DataObject $rawRateRequest
     * @return RequestInterface[]
     * @throws NoServiceFoundForProduct|NoSuchEntityException
     * @TODO add integrity validation logic for a case in which products have different carriers available
     */
    public function collectRequests(RateRequest $rateRequest, DataObject $rawRateRequest): array
    {
        $requests = [];
        $countryCode = $rawRateRequest->getDestCountry();
        /** @var Item $item */
        foreach ($rateRequest->getAllItems() as $item) {
            if ($item->getParentItemId()) {
                continue;
            }

            $itemRequests = [];
            $product = $this->productRepository->get($item->getSku());
            foreach ($this->dataProvider->getServicesByDestCountryCode($countryCode) as $service) {
                try {
                    $request = $this->requestBuilder->build(
                        $this->packageBuilder->build($service, $product),
                        $service,
                        $rawRateRequest
                    );
                    $qty = $item->getQty();
                    while ($qty--) {
                        $itemRequests[] = $request;
                    }
                } catch (NoPackageCreatedForService) {
                    continue;
                }
            }

            if (!$itemRequests) {
                throw new NoServiceFoundForProduct;
            }

            $requests = array_merge($requests, $itemRequests);
        }

        return $requests;
    }
}
