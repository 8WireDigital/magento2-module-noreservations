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

class SourceDeductionProcessor implements ObserverInterface
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
     * @param GetSourceSelectionResultFromInvoice $getSourceSelectionResultFromInvoice
     * @param SourceDeductionServiceInterface $sourceDeductionService
     * @param SourceDeductionRequestsFromSourceSelectionFactory $sourceDeductionRequestsFromSourceSelectionFactory
     * @param SalesEventInterfaceFactory $salesEventFactory
     * @param ItemToSellInterfaceFactory $itemToSellFactory
     * @param PlaceReservationsForSalesEventInterface $placeReservationsForSalesEvent
     */
    public function __construct(
        GetSourceSelectionResultFromInvoice $getSourceSelectionResultFromInvoice,
        SourceDeductionServiceInterface $sourceDeductionService,
        SourceDeductionRequestsFromSourceSelectionFactory $sourceDeductionRequestsFromSourceSelectionFactory,
        SalesEventInterfaceFactory $salesEventFactory,
        ItemToSellInterfaceFactory $itemToSellFactory,
        PlaceReservationsForSalesEventInterface $placeReservationsForSalesEvent
    ) {
        $this->getSourceSelectionResultFromInvoice = $getSourceSelectionResultFromInvoice;
        $this->sourceDeductionService = $sourceDeductionService;
        $this->sourceDeductionRequestsFromSourceSelectionFactory = $sourceDeductionRequestsFromSourceSelectionFactory;
        $this->salesEventFactory = $salesEventFactory;
        $this->itemToSellFactory = $itemToSellFactory;
        $this->placeReservationsForSalesEvent = $placeReservationsForSalesEvent;
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
