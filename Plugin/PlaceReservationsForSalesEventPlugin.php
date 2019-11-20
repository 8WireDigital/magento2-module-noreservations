<?php
namespace EightWire\NoReservations\Plugin;

use Magento\InventorySales\Model\PlaceReservationsForSalesEvent;

class PlaceReservationsForSalesEventPlugin
{
    protected $productExtensionFactory;
    protected $helper;

    /**
     * Interceptor which doesn't fire the callback function meaning that any code using PlaceReservationsForSalesEventInterface
     * to place a reservation will not actually persist the change. We are doing this because we want to deduct stock
     * as soon as an order is invoiced and we don't really need the reservations functionality at all
     *
     * @param PlaceReservationsForSalesEvent $subject
     * @param callable $proceed
     */
    public function aroundExecute(
        PlaceReservationsForSalesEvent $subject,
        callable $proceed
    ) {
        return;
    }
}
