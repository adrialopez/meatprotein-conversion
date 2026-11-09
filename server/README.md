# Archivos que viven en el servidor (no son parte del plugin)

- `mpc-portugal-shipping.php`: mu-plugin instalado el 2026-11-09 en `wp-content/mu-plugins/` de meatprotein.eu.
  Devuelve a Portugal su tarifa (15 € IVA incl.) después de Advanced Dynamic Pricing, que daba envío gratis también a Portugal.
  Quitarlo cuando el plugin `meatprotein-conversion` lo incorpore o si se retira ADP.
- `snippets/`: código actual de los snippets de Code Snippets (HPOS) 16 («Método de pago en listado») y 20 («Lista de Picking», por defecto pedidos por procesar desde el primero en proceso). Los originales están en `~/backups/` del servidor.
