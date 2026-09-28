# SECOP Suite v5.19.0 - Guia de Instalacion en WordPress

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

1. Descargar `secop-suite.zip` de los **Releases** del repositorio (no el "Download ZIP" de GitHub; ver "Actualizar sin perder datos")
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

## Actualizar sin perder datos

> **ADVERTENCIA:** NUNCA use **Plugins > Eliminar** sobre una copia antigua de SECOP Suite
> de las versiones **5.15.0, 5.16.0, 5.17.0 o anteriores**. El `uninstall.php` de esas versiones borra la tabla de
> contratos, la vista de seguimiento, los respaldos, las graficas, los filtros, las cards y toda
> la configuracion. Si tiene una copia duplicada instalada, **desactivela** y **borre su carpeta
> por FTP/SFTP o con el administrador de archivos del hosting** (por ejemplo
> `wp-content/plugins/suite-secop-main/`), nunca con el boton "Eliminar".

La carpeta del plugin en produccion es siempre `wp-content/plugins/secop-suite/`. WordPress
solo reemplaza la version instalada cuando el ZIP que se sube tiene esa misma carpeta raiz
(`secop-suite/`). El boton **Code > Download ZIP** de GitHub genera una carpeta
`suite-secop-<rama>/`, por eso WordPress la instalaba como un plugin distinto: **no use ese ZIP**.

### Opcion A: subir el ZIP del release (recomendada)

1. Descargar `secop-suite.zip` de la seccion **Releases** del repositorio
   (https://github.com/GobernaciondeNarino/suite-secop/releases), no el "Download ZIP".
2. En WordPress ir a **Plugins > Anadir nuevo > Subir plugin**, elegir `secop-suite.zip` y
   pulsar **Instalar ahora**.
3. WordPress detecta que el plugin ya existe y muestra la comparacion de versiones: pulsar
   **Reemplazar el actual con el subido** (WordPress 5.5 o superior).
4. Los datos (tabla de contratos, graficas, filtros, cards, configuracion) se conservan; al
   entrar al administrador el plugin ejecuta las migraciones pendientes.

### Opcion B: desde el escritorio de WordPress

A partir de la version 5.15.1 el actualizador consulta los releases de
`GobernaciondeNarino/suite-secop` (antes apuntaba a un repositorio inexistente y nunca
ofrecia actualizaciones, asi que el primer salto hacia la 5.15.1 debe hacerse con la
Opcion A). Cuando haya un release nuevo aparecera en **Escritorio > Actualizaciones** y en
**Plugins**: pulsar **Actualizar ahora**. El actualizador descarga el asset `secop-suite.zip`,
instala sobre la carpeta existente y reactiva el plugin solo si estaba activo.

### Generar el ZIP (equipo de desarrollo)

```bash
bin/build-zip.sh
# -> dist/secop-suite-<version>.zip y dist/secop-suite.zip (carpeta raiz: secop-suite/)
```

El script toma la version del encabezado `Version:` de `secop-suite.php`, comprueba que
coincida con `SECOP_SUITE_VERSION` y excluye los archivos de desarrollo listados en
`.distignore` (tests, docs, bin, .github, .claude, AUDITORIA.md, REVIEW.md...).

Para publicar un release: actualizar `Version:` y `SECOP_SUITE_VERSION`, hacer commit y
empujar un tag con la misma version:

```bash
git tag v5.15.1
git push origin v5.15.1
```

El workflow `.github/workflows/release.yml` verifica que el tag coincida con la version,
ejecuta las pruebas, construye el ZIP y lo publica como asset del GitHub Release.

### Si ya tiene dos copias instaladas

1. Identificar la copia buena (carpeta `secop-suite/`). Desde la version 5.15.1, si hay dos copias,
   el plugin muestra un aviso en el administrador indicando que carpeta esta en uso y cual sobra,
   sin error fatal.
2. **Desactivar** la copia sobrante en **Plugins**.
3. **Borrar su carpeta por FTP/SFTP o administrador de archivos**. No usar "Eliminar".
4. Si la copia buena no esta en `secop-suite/`, subir el ZIP del release (Opcion A) y
   reemplazar.

### Datos al desinstalar

Desde la version 5.15.1, eliminar el plugin **conserva todos los datos** por defecto. Solo se
borran si se marca **SECOP Suite > Configuracion > Importar datos > Eliminar todos los datos
al desinstalar el plugin**, y aun asi no se borran si existe otra copia de SECOP Suite en
`wp-content/plugins/`.

## Configuracion Inicial

### Paso 1: Acceder al panel

Tras activar el plugin, aparecera un nuevo menu **SECOP Suite** en la barra lateral del admin con icono de grafica.

### Paso 2: Configurar la API

1. Ir a **SECOP Suite > Configuracion** (pestaña **Importar datos**)
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

1. Ir a **SECOP Suite > Configuracion** (pestaña **Registros**)
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

1. Ir a **SECOP Suite > Configuracion** (pestaña **Importar datos**)
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

Tras la instalacion, verifique en **SECOP Suite > Configuracion** (pestaña **Logs**) que:

- La version del plugin es 5.16.0
- La version de PHP cumple el requisito (8.1+)
- El estado del sistema es "Listo"
- WP-Cron esta activo (si usa actualizaciones automaticas)

## Desinstalacion

Al desinstalar el plugin desde **Plugins > Desactivar > Eliminar** se limpian siempre la tarea
cron de importacion y los transients de progreso, pero **se conservan todos los datos** (tabla
de contratos, vista de seguimiento, respaldos, graficas, filtros, cards y opciones) para que
una reinstalacion o actualizacion los reutilice.

Para borrar tambien los datos, marcar antes **Eliminar todos los datos al desinstalar el
plugin** en **SECOP Suite > Configuracion** (pestaña **Importar datos**). Aun con esa casilla marcada, no se
borra nada si hay otra copia de SECOP Suite instalada en `wp-content/plugins/`.

> Las versiones 5.15.0, 5.16.0, 5.17.0 y anteriores borraban todos los datos al eliminar el plugin. Vea
> "Actualizar sin perder datos".

## Herramientas de Desarrollo (repositorio)

El repositorio incluye herramientas de asistencia con IA para el equipo de desarrollo:

- **Claude Code (CLI)**: se instala en la maquina del desarrollador con `npm install -g @anthropic-ai/claude-code` (o desde https://claude.com/claude-code). Al abrir el repositorio, detecta automaticamente las skills incluidas.
- **Skills UI/UX Pro Max** (`.claude/skills/`): 7 skills de diseno UI/UX (ui-ux-pro-max, design, design-system, ui-styling, brand, banner-design, slides) provenientes de https://github.com/nextlevelbuilder/ui-ux-pro-max-skill. Disponibles automaticamente en cualquier sesion de Claude Code sobre este repositorio.
- **Claude Code Action** (`.github/workflows/claude.yml`): mencionar `@claude` en un issue o PR de GitHub invoca al agente para responder o implementar cambios.
- **Security Review** (`.github/workflows/security-review.yml`): revision de seguridad automatica (https://github.com/anthropics/claude-code-security-review) sobre cada pull request, con comentarios en el propio PR.

**Requisito para los workflows**: crear el secret `ANTHROPIC_API_KEY` en GitHub (Settings > Secrets and variables > Actions).

Vea `AUDITORIA.md` para la lista de auditoria de codigo (hallazgos corregidos y pendientes).

## Soporte

- **Repositorio**: https://github.com/GobernaciondeNarino/suite-secop
- **Autor**: Jonnathan Bucheli Galindo - Gobernacion de Narino
- **Licencia**: GPL v2 o posterior
