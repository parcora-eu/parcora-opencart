# Parcora for OpenCart

Shipping extension that connects an OpenCart store to the [Parcora](https://parcora.eu)
API: one integration for parcel delivery across Europe.

- **Live rates at checkout** for every carrier service you enable, priced as the
  Parcora rate plus a markup or as a fixed price, and free above an order subtotal.
- **Parcel lockers and pickup points**: the buyer picks one in the shipping
  method modal, and it is stored with the order.
- **Shipments**: created automatically when an order reaches a chosen status, or
  with one click on the order page.
- **Labels** from the order page (PDF A4, 10x15 or ZPL).
- **Tracking**: a signed webhook updates the shipment status and can move the
  order to "Complete" on delivery.

No core files are modified: everything hooks in through OpenCart events.

## Requirements

- OpenCart 4.0.2 or newer (tested on 4.1.0.4)
- PHP with the cURL extension
- A Parcora API key (`dp_test_…` for testing, `dp_live_…` for real shipments)

OpenCart 3 is planned; see [Layout](#layout).

## Installation

1. Download `parcora.ocmod.zip` (or build it, see [Development](#development)).
   Keep the file name: OpenCart takes the extension code from it.
2. **Extensions > Installer**: upload the archive and install it.
3. **Extensions > Extensions > Shipping**: install **Parcora**, then edit it.

## Configuration

| Tab | What to set |
| --- | --- |
| General | API key, status, tax class, geo zone, default parcel weight |
| Sender | The address parcels are collected from |
| Services | Every service your account can use: enable it, rename it, and set its price (Parcora rate + markup %/amount, or fixed) and free-shipping threshold |
| Orders | The order status that creates the shipment, the status set on delivery, the label format, and **Register webhook** for tracking updates |

Carriers need the buyer's phone number for lockers and pickup points. Turn on
**Telephone Display** and **Telephone Required** in *System > Settings > Option*;
the extension warns you when they are off.

## How it works

| Event | What the extension does |
| --- | --- |
| `catalog/view/checkout/shipping_method/after` | Adds the pickup point picker to the shipping method modal |
| `catalog/model/checkout/order.addHistory/after` | Creates the shipment when the order reaches the configured status |
| `admin/view/sale/order_info/before` | Adds a Parcora tab with the shipment, tracking and label to the order page |

The chosen service, carrier and pickup point travel inside the order's
`shipping_method`, so they appear wherever OpenCart shows the shipping method
(order page, invoices, emails). Shipment ids, tracking and errors are kept in
the `parcora_shipment` table, which is kept on uninstall.

The tracking webhook endpoint is
`index.php?route=extension/parcora/shipping/parcora.webhook`; requests without a
valid `Webhook-Signature` are rejected.

## Development

A local OpenCart 4 stand runs in Docker:

```bash
docker compose up -d --build
```

The store is at http://localhost:8091 and the admin at http://localhost:8091/admin
(`admin` / `admin12345`). To talk to a Parcora API running on the host, set the
API URL to `http://host.docker.internal:8090`.

```bash
bin/build          # dist/oc4/parcora.ocmod.zip
bin/sync           # copy the working tree into the stand's installed extension
composer test      # unit tests for the shared core
```

## Layout

```
src/core/   Version-agnostic PHP (7.4+): API client, pricing, webhook signatures,
            shipment payloads, weights, the shipment table. No OpenCart classes.
src/oc4/    The OpenCart 4 extension (admin, catalog, templates, checkout script).
bin/build   Packs src/oc4 with src/core in system/library/parcora.
```

An OpenCart 3 build goes into `src/oc3/` with its own controllers, models and
templates on top of the same core, and becomes a second target in `bin/build`.

The official [parcora-php](https://github.com/parcora-eu/parcora-php) SDK is not
used because it needs PHP 8.2 and Composer, which OpenCart extensions cannot rely on.

## Limitations

- Pickup point selection is built for the default OpenCart checkout; third-party
  one-page checkouts need their own adaptation.
- Each order ships as one parcel with the total product weight; parcel
  dimensions are not sent yet.
- Cash on delivery is not passed to carriers yet.
- English (`en-gb`) only.

## License

MIT
