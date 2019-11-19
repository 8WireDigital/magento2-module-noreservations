# No Reservations Magento 2 Module

Magento 2.3 introduced [MSI](https://github.com/magento/inventory) which was a complete overhaul of its inventory management system enabling use of multiple source inventories. A new concept introduced in MSI is [reservations](https://devdocs.magento.com/guides/v2.3/inventory/reservations.html). When combined with an ERP system which is the source of truth for stock data these reservations can be problematic if the ERP is updating Magento with levels of salable stock rather than physical stock on hand.

This module attempts to get around some of these issues by doing the following:

* Stopping any reservations being persisted to the database.
* Changing when Magento deducts inventory from sources to when an order is invoiced rather than when it is shipped. 

We are basically reverting to a behaviour more like Magento 2.2 where stock qty is also the saleable qty. 

This is not a one size fits all solution, it was written for a specific use case. That said it has worked well for us so far and may be useful for merchants with similar requirements. It has been used on Magento 2.3.1 and 2.3.2 sites.

Provided by [8 Wire Digital](https://www.8wiredigital.co.nz/)
