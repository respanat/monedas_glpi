# Monedas para GLPI

Plugin para **GLPI 11** que muestra la información financiera de los activos con la moneda local y el formato de cada país, en lugar de los números crudos de la base de datos.

| Hoy en GLPI | Con el plugin |
|---|---|
| Precio de compra `4109900.0000` | `$ 4.109.900 COP` |
| VNC `1 137 833.43` | `$ 1.137.833 COP` |

## Qué hace (v0.1)

- **Configuración por entidad** (pestaña *Moneda* en cada entidad): moneda local, símbolo y su posición, decimales a mostrar (0–4), separador de miles y separador decimal, y si se muestra el código ISO. Las subentidades heredan de la entidad padre hasta que tengan su propia configuración.
- **Ficha financiera de los activos** (pestaña *Gestión*): el precio de compra y el precio de extensión de garantía se muestran y se escriben con formato local. Los valores calculados de solo lectura (VNC, CTP, CTP mensual) también se muestran con formato.
- **No cambia los datos guardados**: el formato se aplica solo en pantalla. En la base de datos se sigue guardando el número limpio (`4109900.0000`), así que desinstalar el plugin no rompe nada.
- **Red de seguridad en el servidor**: si llega un importe con formato (por ejemplo `4.109.900`), el plugin lo convierte a número antes de guardarlo.

## Instalación

La carpeta del plugin **debe llamarse `monedas`**:

```bash
cd /var/www/html/glpi/plugins
git clone https://github.com/respanat/monedas_glpi.git monedas
chown -R www-data:www-data monedas
```

Luego, en GLPI: *Configuración > Plugins > Monedas > Instalar > Activar*.

La entidad raíz queda configurada con pesos colombianos sin decimales (`$ 4.109.900 COP`). Para cambiarlo: *Administración > Entidades > (entidad) > pestaña Moneda*, o el botón de configuración del plugin.

## Cómo interpreta lo que escribe el usuario

Con la configuración colombiana (miles `.`, decimal `,`):

| Escrito | Guardado |
|---|---|
| `4.109.900` | `4109900` |
| `$ 4.109.900 COP` | `4109900` |
| `1250,5` | `1250.5` |
| `4109900.0000` | `4109900` |
| `1.250` | `1250` (tres dígitos tras el punto = miles) |

## Estructura

```
setup.php               registro del plugin y hooks
hook.php                instalación / desinstalación (tabla glpi_plugin_monedas_configs)
src/Config.php          configuración por entidad, herencia, formateo y lectura de importes
front/config.php        acceso desde Configuración > Plugins
front/config.form.php   guarda la configuración
ajax/config.php         configuración en JSON para el navegador
templates/              pestaña "Moneda" en entidades
public/js/monedas.js    formato de los campos en el navegador
public/css/monedas.css
```

## Hoja de ruta

- **v0.2 – Compras en moneda extranjera**: moneda de compra por activo (ej. USD), tasa de cambio (TRM) del día de compra y conversión automática a la moneda local para la amortización.
- **v0.3 – Tasas de cambio**: historial de tasas, carga manual y actualización automática diaria de la TRM.
- **v0.4 – Otras vistas**: listados y búsquedas, costos de contratos, tickets y presupuestos.

## Licencia

GPLv3 o posterior.
