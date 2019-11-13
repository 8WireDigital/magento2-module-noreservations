# No Reservations Magento 2 Module

Magento 2.3 introduced [MSI](https://github.com/magento/inventory) which was a complete overhaul of its inventory management system enabling use of multiple source inventories. A new concept introduced in MSI is [reservations](https://devdocs.magento.com/guides/v2.3/inventory/reservations.html). When combined with an ERP system which is the source of truth for stock data these reservations can be problematic if the ERP is updating Magento with stock levels of salable products rather than physical ones.

This module attempts to get around some of these issues by stopping any reservations being persisted to the database, it also changes when Magento deducts qty from the stocks to when an order is invoiced rather than when it is shipped. We are basically reverting to a behaviour more like Magento 2.2 where stock qty is also the saleable qty. 

This is not a one size fits all solution was written for a specific use case but may be useful for merchants with similar requirements. It has been tested and used on Magento 2.3.1 and 2.3.2 sites.

Provided by [8 Wire Digital](https://www.8wiredigital.co.nz/)