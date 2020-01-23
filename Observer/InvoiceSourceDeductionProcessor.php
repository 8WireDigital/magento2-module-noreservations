<?php
/**
 * Replaces behaviour found in Magento\InventoryShipping\Observer\SourceDeductionProcessor which is fired when an order
 * is shipped. This observer is more like Magento\InventoryShipping\Observer\VirtualSourceDeductionProcessor which is
 * used for virtual products and deducts stock on invoice
 */
declare(strict_types=1);

namespace EightWire\NoReservations\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Exception\LocalizedException;
use Magento\InventorySalesApi\Api\Data\SalesEventInterface;
use Magento\InventorySalesApi\Api\Data\SalesEventInterfaceFactory;
use EightWire\NoReservations\Model\GetSourceSelectionResultFromInvoice;
use Magento\InventorySourceDeductionApi\Model\SourceDeductionRequestInterface;
use Magento\InventorySourceDeductionApi\Model\SourceDeductionServiceInterface;
use Magento\InventoryShipping\Model\SourceDeductionRequestsFromSourceSelectionFactory;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\InventorySalesApi\Api\Data\ItemToSellInterfaceFactory;
use Magento\InventorySalesApi\Api\PlaceReservationsForSalesEventInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class InvoiceSourceDeductionProcessor implements ObserverInterface
{
    /**
     * @var GetSourceSelectionResultFromInvoice
     */
    private $getSourceSelectionResultFromInvoice;

    /**
     * @var SourceDeductionServiceInterface
     */
    private $sourceDeductionService;

    /**
     * @var SourceDeductionRequestsFromSourceSelectionFactory
     */
    private $sourceDeductionRequestsFromSourceSelectionFactory;

    /**
     * @var SalesEventInterfaceFactory
     */
    private $salesEventFactory;

    /**
     * @var ItemToSellInterfaceFactory
     */
    private $itemToSellFactory;

    /**
     * @var PlaceReservationsForSalesEventInterface
     */
    private $placeReservationsForSalesEvent;

    /**
     * @var DataPersistorInterface
     */
    private $dataPersistor;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param GetSourceSelectionResultFromInvoice $getSourceSelectionResultFromInvoice
     * @param SourceDeductionServiceInterface $sourceDeductionService
     * @param SourceDeductionRequestsFromSourceSelectionFactory $sourceDeductionRequestsFromSourceSelectionFactory
     * @param SalesEventInterfaceFactory $salesEventFactory
     * @param ItemToSellInterfaceFactory $itemToSellFactory
     * @param PlaceReservationsForSalesEventInterface $placeReservationsForSalesEvent
     * @param DataPersistorInterface $dataPersistor
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        GetSourceSelectionResultFromInvoice $getSourceSelectionResultFromInvoice,
        SourceDeductionServiceInterface $sourceDeductionService,
        SourceDeductionRequestsFromSourceSelectionFactory $sourceDeductionRequestsFromSourceSelectionFactory,
        SalesEventInterfaceFactory $salesEventFactory,
        ItemToSellInterfaceFactory $itemToSellFactory,
        PlaceReservationsForSalesEventInterface $placeReservationsForSalesEvent,
        DataPersistorInterface $dataPersistor,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager
    ) {
        $this->getSourceSelectionResultFromInvoice = $getSourceSelectionResultFromInvoice;
        $this->sourceDeductionService = $sourceDeductionService;
        $this->sourceDeductionRequestsFromSourceSelectionFactory = $sourceDeductionRequestsFromSourceSelectionFactory;
        $this->salesEventFactory = $salesEventFactory;
        $this->itemToSellFactory = $itemToSellFactory;
        $this->placeReservationsForSalesEvent = $placeReservationsForSalesEvent;
        $this->dataPersistor = $dataPersistor;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
    }

    /**
     * @param EventObserver $observer
     * @return void
     */
    public function execute(EventObserver $observer)
    {
        /** @var \Magento\Sales\Model\Order\Invoice $invoice */
        $invoice = $observer->getEvent()->getInvoice();
        if (!$this->isValid($invoice)) {
            return;
        }

        // if module is enabled in configuration for the order website, return observer
        $websiteId = (int)$this->storeManager->getStore($invoice->getStoreId())->getWebsiteId();
        $websiteCode = $this->storeManager->getWebsite($websiteId)->getCode();
        $enabled = $this->scopeConfig->getValue('eightwirenoreservations/options/enabled', ScopeInterface::SCOPE_WEBSITE, $websiteCode);
        if(!$enabled){
            return;
        }

        // get the persitant variable to avoid the execution of this observer more than once
        if ($this->dataPersistor->get('noreservation_invoice_deduction_success') === $invoice->getOrder()->getIncrementId()) {
            return;
        }

        $sourceSelectionResult = $this->getSourceSelectionResultFromInvoice->execute($invoice);

        /** @var SalesEventInterface $salesEvent */
        $salesEvent = $this->salesEventFactory->create([
            'type' => SalesEventInterface::EVENT_INVOICE_CREATED,
            'objectType' => SalesEventInterface::OBJECT_TYPE_ORDER,
            'objectId' => $invoice->getOrderId(),
        ]);

        $sourceDeductionRequests = $this->sourceDeductionRequestsFromSourceSelectionFactory->create(
            $sourceSelectionResult,
            $salesEvent,
            (int)$invoice->getOrder()->getStore()->getWebsiteId()
        );

        foreach ($sourceDeductionRequests as $sourceDeductionRequest) {
            $this->sourceDeductionService->execute($sourceDeductionRequest);
        }

        // set a persitant variable to avoid the execution of thi observer twice
        $this->dataPersistor->set('noreservation_invoice_deduction_success', $invoice->getOrder()->getIncrementId());
    }

    /**
     * @param InvoiceInterface $invoice
     * @return bool
     */
    private function isValid(InvoiceInterface $invoice): bool
    {
        if ($invoice->getOrigData('entity_id')) {
            return false;
        }

        return true;
    }
}
