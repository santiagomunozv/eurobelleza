En revisiones con cliente surgieron ciertos cambios (no drásticos, más bien un poco de forma) que debemos ajustar, entre esos cambios tenemos lo siguiente:

- Vamos a agregar a la tabla de configuración de siesa (siesa_general_configurations) 3 campos adicionales: lista_precio_flete, lista_precio_obsequio, motivo_obsequio
- así mismo se va a agregar en el flujo actual, es decir, en el formulario de admin/siesa/configuration
- esto nos implica modificar el SiesaFlatFileGenerator ya que por ahí hay un valor fijo ($listaPrecio = $isShipping ? '900' : $config->lista_precio;) cuando es flete (isShipping) no debe tomar ese 900 sino lo que tenga en lista_precio_flete
- Hay productos que van a venir con valor y el descuento va a ser el mismo valor por lo que dará en 0 (son llamados productos de obsequio), estos productos van a ir con la lista de precio que tenga lista_precio_obsequio y el motivo que tenga motivo_obsequio, únicamente los que el total del producto de 0, por ejemplo:
  {
  "id":17406215913643,
  "admin_graphql_api_id":"gid:\/\/shopify\/LineItem\/17406215913643",
  "current_quantity":1,
  "fulfillable_quantity":0,
  "fulfillment_service":"manual",
  "fulfillment_status":"fulfilled",
  "gift_card":false,
  "grams":300,
  "name":"Spray Desenredante para Ni\u00f1as - 250 ML",
  "price":"24000.00",
  "price_set":{
  "shop_money":{
  "amount":"24000.00",
  "currency_code":"COP"
  },
  "presentment_money":{
  "amount":"24000.00",
  "currency_code":"COP"
  }
  },
  "product_exists":true,
  "product_id":6746787938475,
  "properties":[
        ],
        "quantity":1,
        "requires_shipping":true,
        "sku":"10719",
        "taxable":true,
        "title":"Spray Desenredante para Ni\u00f1as",
        "total_discount":"24000.00",
        "total_discount_set":{
        "shop_money":{
            "amount":"24000.00",
            "currency_code":"COP"
        },
        "presentment_money":{
            "amount":"24000.00",
            "currency_code":"COP"
        }
        },
        "variant_id":42857269166251,
        "variant_inventory_management":"shopify",
        "variant_title":"250 ML",
        "vendor":"Eurobelleza",
        "tax_lines":[
        {
            "channel_liable":false,
            "price":"0.00",
            "price_set":{
                "shop_money":{
                    "amount":"0.00",
                    "currency_code":"COP"
                },
                "presentment_money":{
                    "amount":"0.00",
                    "currency_code":"COP"
                }
            },
            "rate":0.19,
            "title":"VAT"
        }
        ],
        "duties":[

        ],
        "discount_allocations":[
        {
            "amount":"24000.00",
            "amount_set":{
                "shop_money":{
                    "amount":"24000.00",
                    "currency_code":"COP"
                },
                "presentment_money":{
                    "amount":"24000.00",
                    "currency_code":"COP"
                }
            },
            "discount_application_index":0
        }
        ]
    }

Eso normalmente se calcula acá en el SiesaFlatFileGenerator

$basePrice = floatval($lineItem['price'] ?? 0);
$discountAllocations = $lineItem['discount_allocations'] ?? [];
$discountAmount = !empty($discountAllocations) ? floatval($discountAllocations[0]['amount'] ?? 0) : 0;
$finalPrice = $basePrice - $discountAmount;

- Adicional, van a llegar medios de pago de esta manera "payment_gateway_names":[
  "manual"
  ],
  Necesito, que cuando lleguen así, dentro del json busques '"tags":"link de pago Addi, Melonn, Melonn-Entregado"', allí en esa etiqueta estará en texto libre la forma de pago correcta que debe ir, por lo que debes buscar en siesa_payment_gateway_mappings que el payment_gateway_name coincida con alguna palabra que esté ahí

{
"siesa_payment_gateway_mappings": [
{
"id" : 1,
"payment_gateway_name" : "Addi Payment",
"sucursal" : "01",
"condicion_pago" : "30",
"centro_costo" : "021014",
"created_at" : "2026-03-07T22:14:20.000Z",
"updated_at" : "2026-03-07T22:14:20.000Z"
},
{
"id" : 2,
"payment_gateway_name" : "Checkout Mercado Pago",
"sucursal" : "02",
"condicion_pago" : "30",
"centro_costo" : "021014",
"created_at" : "2026-03-07T22:14:20.000Z",
"updated_at" : "2026-03-07T22:14:20.000Z"
},
{
"id" : 3,
"payment_gateway_name" : "Mercado Pago Tarjetas",
"sucursal" : "02",
"condicion_pago" : "30",
"centro_costo" : "021014",
"created_at" : "2026-03-07T22:14:20.000Z",
"updated_at" : "2026-03-07T22:14:20.000Z"
},
{
"id" : 4,
"payment_gateway_name" : "manual",
"sucursal" : "02",
"condicion_pago" : "30",
"centro_costo" : "021014",
"created_at" : "2026-03-07T22:14:20.000Z",
"updated_at" : "2026-03-07T22:14:20.000Z"
}
]}

Por ejemplo en el caso que te paso debería encontrar a Addi (id 1) ya que el tag dice "link de pago Addi" y coincide con la info del primer registro, es decir, no va a tomar el id 4 aunque el payment_gateway_names sea manual, ese manual es porque así lo marcan en shopify pero como tal aca se debe hacer la transformación
