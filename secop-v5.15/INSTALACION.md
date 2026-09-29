# SECOP Suite v4.0.1 - Guia de Instalacion en WordPress

## Requisitos del Sistema

| Requisito | Minimo |
|-----------|--------|
| WordPress | 6.0 o superior |
| PHP | 8.1 o superior |
| MySQL | 5.7+ / MariaDB 10.3+ |
| Memoria PHP | 256 MB recomendado |
| max_execution_time | 300 segundos (para importaciones) |

## Instalacion

### Opcion 1: Desde archivo ZIP

1. Descargar el archivo ZIP del plugin desde el repositorio
2. En el panel de WordPress ir a **Plugins > Añadir nuevo > Subir plugin**
3. Seleccionar el archivo ZIP y hacer clic en **Instalar ahora**
4. Hacer clic en **Activar plugin**

### Opcion 2: Via FTP/SFTP

1. Descomprimir el archivo del plugin
2. Subir la carpeta `secop-suite/` al directorio `/wp-content/plugins/` del servidor
3. En WordPress ir a **Plugins > Plugins instalados**
4. Buscar "SECOP Suite" y hacer clic en **Activar**

### Opcion 3: Via WP-CLI

```bash
# Copiar la carpeta al directorio de plugins
cp -r secop-suite /ruta/a/wordpress/wp-content/plugins/

# Activar el plugin
wp plugin activate secop-suite
```

## Configuracion Inicial

### Paso 1: Acceder al panel

Tras activar el plugin, aparecera un nuevo menu **SECOP Suite** en la barra lateral del admin con icono de grafica.

### Paso 2: Configurar la API

1. Ir a **SECOP Suite > Importar Datos**
2. En la seccion "Configuracion", completar:
   - **URL de la API**: `https://www.datos.gov.co/resource/jbjy-vk9h.json` (predeterminada)
   - **NIT de la Entidad**: El NIT de su entidad (ej: `800103923`)
   - **Rango de Fechas**: Definir desde y hasta que fecha importar contratos
3. Hacer clic en **Guardar Configuracion**

### Paso 3: Primera importacion

1. En la misma pagina, hacer clic en **Iniciar Importacion**
2. Esperar a que termine el proceso (se muestra barra de progreso)
3. La importacion se ejecuta en segundo plano; puede cerrar la pagina

### Paso 4: Verificar datos

1. Ir a **SECOP Suite > Ver Registros**
2. Verificar que los contratos se cargaron correctamente
3. Usar los filtros de busqueda, ano y estado para explorar los datos

## Crear Graficas

1. Ir a **SECOP Suite > Graficas > Nueva Grafica**
2. Asignar un titulo a la grafica
3. Configurar:
   - **Tipo de grafica**: Barras, Lineas, Area, Pie, Donut, Treemap, Apiladas o Agrupadas
   - **Tabla de datos**: Seleccionar la tabla fuente
   - **Campo X**: La categoria o campo de agrupacion
   - **Campo Y**: El valor numerico a agregar
   - **Funcion de agregacion**: SUM, COUNT, AVG, MAX o MIN
4. Hacer clic en **Publicar**
5. Copiar el shortcode mostrado en la barra lateral: `[secop_chart id="XX"]`

## Insertar Graficas en Paginas

Usar el shortcode en cualquier pagina o entrada:

```
[secop_chart id="123"]
```

Parametros opcionales:

```
[secop_chart id="123" height="500" class="mi-clase-css"]
```

## Actualizacion Automatica

Para programar importaciones automaticas:

1. Ir a **SECOP Suite > Importar Datos > Configuracion**
2. Activar **Actualizacion Automatica**
3. Seleccionar frecuencia: Diario, Semanal o Mensual
4. Guardar configuracion

**Nota:** Requiere que WP-Cron este activo. En servidores con cron del sistema, configurar:

```bash
# Agregar al crontab del servidor (cada 15 minutos)
*/15 * * * * wget -q -O - https://su-sitio.com/wp-cron.php?doing_wp_cron > /dev/null 2>&1
```

## API REST

El plugin expone endpoints publicos:

| Endpoint | Descripcion |
|----------|-------------|
| `GET /wp-json/secop-suite/v1/contracts` | Lista contratos con paginacion |
| `GET /wp-json/secop-suite/v1/contracts/{id}` | Detalle de un contrato |
| `GET /wp-json/secop-suite/v1/stats` | Estadisticas generales |
| `GET /wp-json/secop-suite/v1/chart/{id}/data` | Datos de una grafica |
| `GET /wp-json/secop-suite/v1/chart/{id}/csv` | Descargar CSV |

Parametros de filtrado para `/contracts`:

```
?per_page=20&page=1&anno=2024&estado=Aprobado&search=texto&fecha_desde=2024-01-01&fecha_hasta=2024-12-31
```

## Comandos WP-CLI

```bash
# Ejecutar importacion manual
wp secop import

# Importar con parametros especificos
wp secop import --nit=800103923 --desde=2020-01-01 --hasta=2024-12-31

# Ver estadisticas
wp secop stats

# Eliminar todos los datos (requiere confirmacion)
wp secop truncate --yes
```

## Librerias JavaScript (Opcional)

Para mayor seguridad y rendimiento, descargue las librerias JS localmente:

```bash
cd wp-content/plugins/secop-suite/assets/js/vendor/

# D3.js v5
curl -o d3.v5.min.js https://d3js.org/d3.v5.min.js

# D3plus v2
curl -o d3plus.min.js https://cdn.jsdelivr.net/npm/d3plus@2

# TopoJSON v2
curl -o topojson.v2.min.js https://d3js.org/topojson.v2.min.js

# html2canvas
curl -o html2canvas.min.js https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js
```

El plugin detecta automaticamente si los archivos locales existen y los usa en lugar de los CDNs.

## Configuracion Nginx (Importante)

Si usa Nginx, agregue esta regla para proteger los logs del plugin:

```nginx
location ~* /wp-content/plugins/secop-suite/logs/ {
    deny all;
    return 404;
}
```

## Verificar Instalacion

Tras la instalacion, verifique en **SECOP Suite > Logs** que:

- La version del plugin es 4.0.1
- La version de PHP cumple el requisito (8.1+)
- El estado del sistema es "Listo"
- WP-Cron esta activo (si usa actualizaciones automaticas)

## Desinstalacion

Al desinstalar el plugin desde **Plugins > Desactivar > Eliminar**, se eliminan automaticamente:

- La tabla de contratos de la base de datos
- Todas las opciones del plugin
- Todos los posts de graficas y su metadata
- Los transients de progreso
- Las tareas cron programadas

## Soporte

- **Repositorio**: https://github.com/GobernaciondeNarino/secop-suite
- **Autor**: Jonnathan Bucheli Galindo - Gobernacion de Narino
- **Licencia**: GPL v2 o posterior
