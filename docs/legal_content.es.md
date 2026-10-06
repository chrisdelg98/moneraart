# Contenido Legal — Borrador en Español

> Versión en español de [legal_content.en.md](./legal_content.en.md).
> Los requisitos técnicos de aceptación, versionado y almacenamiento están en §7.7 de
> [implementation_plan.md](./implementation_plan.md). La arquitectura bilingüe está en §4.7.
> **Este archivo contiene el texto que lee el cliente.**

---

## ⚠️ Antes de publicar

**Esto es un borrador de trabajo, no asesoría legal.** Está modelado sobre las convenciones que usan
tiendas establecidas de activos digitales y arte imprimible. Tres partes necesitan revisión
profesional antes de salir:

1. **La cláusula de no reembolso.** Su validez depende de la ley de protección al consumidor del
   país del *comprador*, no del vendedor. La §7.7.3 del plan explica por qué el texto del checkout
   pesa más que el de la política.
2. **Las secciones de IA y licencia.** El estatus de copyright de las imágenes generadas con IA no
   está resuelto y varía según el país. El enfoque aquí — vender una **licencia de uso de archivos**,
   nunca una cesión de copyright — es deliberado, y la §1 explica por qué.
3. **Los marcadores de identidad y jurisdicción.** Unos términos que no dicen quién vende ni qué ley
   los rige valen mucho menos en una disputa.

Presupuesta una consulta de una hora con un abogado del país de establecimiento. A este nivel de
ingresos no vale más que eso, y sí vale eso.

### Nota sobre esta traducción

**No es una traducción literal, y no debe serlo.** El inglés y el español comercial tienen registros
distintos: lo que en inglés suena cálido y directo, traducido palabra por palabra al español suena
seco o incluso brusco. Esta versión traduce **el tono**, siguiendo la guía de voz de la §1 del
documento en inglés.

Dos diferencias deliberadas:

- **Tuteo, no "usted".** El "usted" crea distancia formal que no encaja con una tienda de arte
  pequeña e independiente. El tuteo es lo estándar en comercio digital en español y suena cercano
  sin perder profesionalismo.
- **Español neutro.** Sin "vosotros", sin modismos regionales, sin voseo. La tienda vende a todo el
  mundo hispanohablante, y cualquier marca regional excluye al resto de lectores.

**Marcadores a completar antes de publicar:**

```
[NOMBRE_TIENDA]       Monera Art
[ENTIDAD_LEGAL]       razón social, o el nombre del propietario si es persona física
[PAIS]                país de establecimiento — aún sin decidir, ver plan §24.2
[JURISDICCION]        ley aplicable y tribunales
[EMAIL_SOPORTE]       soporte@...
[SITIO_WEB]           https://...
[FECHA_VIGENCIA]      fecha de publicación
[PLAZO_RESPUESTA]     un plazo de respuesta que puedas cumplir de verdad — 48 horas es realista
[MONEDA]              USD
[HORAS_EXPIRACION]    validez de los enlaces — el plan usa 72 por defecto
[MAX_DESCARGAS]       descargas por archivo — el plan usa 5 por defecto
```

Se completan por pedido al renderizar, no antes de publicar:

```
[NUMERO_PEDIDO]  [FECHA_PEDIDO]  [TITULO_PRODUCTO]
```

---

## 1. Guía de voz

Las cuatro reglas del documento en inglés, aplicadas al español. Importan más que las palabras
exactas, porque son lo que mantiene el tono cuando alguien más escriba.

### Regla 1 — Dilo todo. Empieza por lo que sí haces, no por lo que no eres.

Una negación hace que el lector busque el problema escondido. La misma información en positivo deja
que llegue solo a la conclusión — y confía más en una conclusión propia.

| En vez de | Escribe |
|---|---|
| "No somos ilustradores tradicionales ni pretendemos serlo" | "Cualquiera puede escribir un prompt. El trabajo está en todo lo que viene después: criterio, oficio, preparación, curaduría." |
| "No podemos garantizar exclusividad" | "Tu licencia cubre uso personal. Para derechos exclusivos o comerciales, escríbenos." |
| "Si esto no es lo que buscas, no compres" | "¿Tienes dudas de si encaja en tu espacio? Escríbenos." |

El lector recibe los mismos hechos en ambas columnas. Solo una suena segura.

### Regla 2 — No te disculpes por tu propio producto.

"Preferimos decírtelo antes que quedarnos con tu dinero" se escribe como honestidad y se lee como
duda. Si el producto vale la pena, preséntalo así y deja que la información conviva al lado, como
dato, no como advertencia.

### Regla 3 — Describe situaciones, no errores del cliente.

"No leíste la descripción" culpa justo cuando alguien ya está frustrado. "Un tamaño que resultó no
ser el que querías" cubre el mismo caso sin quitarle dignidad. La política no cambia; la relación sí.

### Regla 4 — Cierra cada límite con una puerta.

Toda restricción debería terminar con un camino. "No cubierto por esta licencia" se convierte en "no
cubierto — escríbenos sobre uso comercial". Un callejón sin salida invita a una disputa. Una puerta
invita a un correo.

### Sobre cómo se dice lo de la IA

La formulación del propietario — *no somos los creadores digitales del arte, pero sí somos quienes
generamos las imágenes* — contiene una distinción real, y el texto conserva ambas mitades:

- **No** dibujaste ni pintaste estas piezas a mano.
- **Sí** las generaste: concepto, prompts, selección entre muchos resultados, edición, escalado,
  preparación para impresión, armado de colecciones.

El texto lo resuelve **describiendo el trabajo que sí haces** en vez de negar el que no. "Creado con
herramientas de IA, y trabajado por nosotros" lo dice completo, en nueve palabras, sin una sola
disculpa. La transparencia es total; el tono es seguro.

---

## 2. Términos de Venta

> **Términos de Venta**
> Última actualización: [FECHA_VIGENCIA] · Versión 1.0

### 1. Quiénes somos

[SITIO_WEB] ("[NOMBRE_TIENDA]", "nosotros") es operado por [ENTIDAD_LEGAL], con sede en [PAIS].

Puedes escribirnos a [EMAIL_SOPORTE]. Respondemos todos los mensajes en [PLAZO_RESPUESTA].

### 2. Qué vendemos

Vendemos **archivos digitales**: arte imprimible que recibes como descarga inmediata.

Todo aquí es digital. Lo imprimes tú, o lo llevas a la imprenta que prefieras. No enviamos nada
físico.

### 3. Cómo hacemos nuestro arte

Nuestro arte se crea con herramientas de generación de imágenes con IA, y después lo trabajamos
nosotros.

Esto es lo que lleva cada pieza:

- Partimos de una idea: un ambiente, una paleta, el espacio al que pertenece.
- Generamos, miramos, ajustamos y volvemos a generar, refinando hasta que algo encaja.
- Nos quedamos solo con lo que colgaríamos en nuestra propia pared. La mayoría no pasa este filtro.
- Lo que sobrevive se edita, se corrige y se escala.
- Preparamos cada archivo para impresión a 300 DPI, en medidas que caben en marcos reales.
- Agrupamos las piezas que se acompañan en sets y colecciones.

Así que lo que compras es un archivo curado y listo para imprimir: la selección, la preparación, los
formatos y la entrega inmediata.

Te lo contamos abiertamente porque es parte de cómo trabajamos. La versión larga está aquí:
**[Cómo hacemos nuestro arte →]**

> 📋 **Para ti, no para el cliente.** Esta cláusula hace trabajo real: un comprador al que se le dijo
> claramente que el arte es generado con IA no puede alegar después que fue engañado. Mantenla
> visible y en lenguaje simple. El distintivo en la página de producto (§7) la refuerza justo en el
> momento de decidir, que es donde cuenta.

### 4. Qué puedes hacer con tus archivos

Tu compra incluye una **licencia de uso personal**.

**Puedes:**

- Imprimir para tu casa, tu oficina o tu espacio
- Imprimir tantas copias como quieras, para ti
- Imprimir en casa o en cualquier imprenta
- Redimensionar o recortar para que encaje en tu marco
- Regalar una copia impresa

**Por favor, no:**

- Compartir, revender ni redistribuir los archivos digitales
- Subirlos a sitios web, marketplaces, bancos de imágenes o plataformas de impresión bajo demanda
- Vender impresiones, productos o mercancía hechos con ellos
- Usarlos comercialmente ni en publicidad
- Presentar el arte como creación propia
- Incluirlos en nada que distribuyas: plantillas, cursos, paquetes

**¿Tienes un proyecto comercial?** Hay licencias extendidas disponibles para muchas piezas. Escribe
a [EMAIL_SOPORTE] y lo resolvemos.

### 5. Sobre la propiedad

Al comprar recibes una licencia para usar los archivos. El arte sigue siendo nuestro.

Vamos a ser claros con una cosa: el estatus de copyright de las imágenes generadas con IA todavía
está desarrollándose, y las reglas cambian de un país a otro. Por eso lo que ofrecemos es una
**licencia para usar los archivos que preparamos para ti**, no una cesión de copyright ni una
promesa de exclusividad.

Lo que sí respaldamos: los archivos que entregamos, preparados por nosotros, y tu derecho a usarlos
tal como se describe arriba. No trabajamos a partir de obras existentes ni las copiamos.

Si tu proyecto necesita derechos exclusivos o propiedad verificada, escríbenos a [EMAIL_SOPORTE] y
te decimos con honestidad si podemos ayudarte.

> 📋 **Para ti, no para el cliente.** Esta es la cláusula más importante del documento. Vender una
> *licencia de uso de archivos* en vez de *el copyright de una imagen* significa que nunca prometes
> algo que quizá no posees legalmente. Y por eso importa la última línea: convierte una petición
> inusual en una conversación, no en una solicitud de reembolso.

### 6. Precios y pago

- Los precios se muestran en [MONEDA] y es exactamente lo que pagas.
- El pago lo gestiona **PayPal**. Los datos de tu tarjeta nunca pasan por nuestros sistemas.
- Puedes pagar con cuenta PayPal o con tarjeta, sin crear una cuenta con nosotros.
- Los precios pueden cambiar, pero el que ves al pagar es el que pagas.

### 7. Entrega

Tus archivos llegan **de inmediato** después del pago.

- Los enlaces de descarga aparecen en la página de confirmación al instante
- Los mismos enlaces llegan a tu correo
- Los enlaces están activos **[HORAS_EXPIRACION] horas**, con hasta **[MAX_DESCARGAS] descargas**
  por archivo
- ¿Los necesitas otra vez? Pide enlaces nuevos desde tu página de pedido o escríbenos. Sin costo,
  mientras tengamos tu pedido en nuestros registros

Un detalle que conviene cuidar: **usa un correo al que tengas acceso.** Es por donde te encuentran
tus archivos.

¿No llegó nada? Revisa tu carpeta de spam y luego escríbenos. Te lo hacemos llegar.

### 8. Reembolsos

Como todo lo que vendemos es digital, **todas las ventas son definitivas**.

En el momento en que se procesa tu pago, los archivos completos son tuyos: descargados, permanentes
e imposibles de devolver. Al completar tu compra confirmas que quieres tus archivos de inmediato, y
que entiendes que por eso no pueden devolverse ni cancelarse una vez entregados.

**Siempre lo resolvemos cuando algo sale mal de verdad.** Escribe a [EMAIL_SOPORTE] si:

- Te cobraron más de una vez
- Tus archivos no abren, o llegaron incompletos
- Lo que recibiste no corresponde con lo que mostraba la página
- Tus enlaces dejaron de funcionar y no podemos repararlos
- Pagaste y no llegó nada

Te mandamos archivos de reemplazo, enlaces nuevos o un reembolso: lo que realmente lo solucione.

**Lo que no podemos reembolsar** son las cosas que quedan fuera de nuestras manos una vez que los
archivos son tuyos: un cambio de parecer, un tamaño o formato que resultó no ser el que querías, o
cómo salió una impresión en casa o en una imprenta.

Una ayuda antes de comprar: cada página de producto tiene imágenes de vista previa, la lista
completa de archivos incluidos y las medidas disponibles. Y si algo no queda claro, pregúntanos.
Preferimos mucho más responder una duda que dejarte con algo que no era lo que buscabas.

> 📋 **Para ti, no para el cliente.** El segundo párrafo hace trabajo legal específico. Los
> compradores de la UE y Reino Unido tienen un derecho de desistimiento de 14 días sobre contenido
> digital que **solo** puede renunciarse mediante consentimiento expreso a la entrega inmediata más
> reconocimiento de que se pierde ese derecho — que es exactamente lo que establecen esa frase y la
> casilla del checkout, juntas. Si quitas cualquiera de las dos, la política deja de sostenerse en
> esos mercados. Nota aparte: la protección al comprador de PayPal opera con independencia de esta
> política, y por eso importan los registros de entrega de la §8.4 del plan.

### 9. Sobre la impresión

Nuestros archivos vienen a **300 DPI** en las medidas que indica cada página de producto: resolución
estándar de impresión.

Cómo queda una impresión depende de tu impresora, tu papel y tu imprenta. Las pantallas y el papel
también manejan el color distinto: una pantalla emite luz y el papel la refleja, así que siempre hay
alguna diferencia.

Dos cosas ayudan: usar una imprenta profesional cuando puedas, e imprimir una prueba pequeña antes
de ir a un tamaño grande.

### 10. Uso del sitio

No hace falta crear cuenta. Te pedimos tu correo para entregarte tu compra, y nada más.

Sí te pedimos que no:

- Compartas, revendas ni publiques tus enlaces de descarga
- Intentes acceder a archivos que no compraste
- Uses herramientas automatizadas para descargar o extraer contenido masivamente
- Interfieras con el funcionamiento del sitio
- Crees pedidos múltiples para saltarte los límites de los productos gratuitos

Podemos revocar el acceso a descargas cuando esto se incumple.

### 11. Disponibilidad

Trabajamos para que el sitio funcione siempre, aunque no podemos prometer que nunca esté fuera de
servicio. Los productos pueden añadirse, cambiar o retirarse con el tiempo. Retirar un producto
nunca afecta a los archivos que ya compraste: esos son tuyos.

### 12. Nuestra responsabilidad

Nuestros archivos se entregan tal como son. Hasta donde la ley lo permita, nuestra responsabilidad
total por cualquier reclamación relacionada con una compra se limita **al importe que pagaste por
ella**.

No respondemos por costos de impresión, materiales, pérdida de beneficios ni daños indirectos o
derivados.

Nada de esto limita responsabilidades que no puedan limitarse legalmente.

### 13. Cambios en estos términos

Podemos actualizar estos términos de vez en cuando. La versión que aplica a tu pedido es la que
aceptaste al comprarlo, y guardamos ese registro: los cambios nunca aplican hacia atrás.

La versión vigente y su fecha están siempre al inicio de esta página.

### 14. Ley aplicable

Estos términos se rigen por las leyes de [JURISDICCION], y cualquier disputa se resolverá ante los
tribunales de [JURISDICCION].

### 15. Escríbenos

[EMAIL_SOPORTE] · Respondemos en [PLAZO_RESPUESTA].

---

## 3. Política de Privacidad

> **Política de Privacidad**
> Última actualización: [FECHA_VIGENCIA] · Versión 1.0

### La versión corta

Guardamos tu correo para enviarte lo que compraste. Lo ciframos. Nunca lo vendemos ni lo
compartimos con fines publicitarios.

Esa es, honestamente, toda la política. El resto de la página es el detalle, para quien lo quiera.

### 1. Qué recopilamos

**Cuando compras:**

| Qué | Para qué | Cómo lo guardamos |
|---|---|---|
| Correo electrónico | Enviarte tus archivos y tu recibo | Cifrado |
| Tu nombre | Personalizar tu recibo | Cifrado, y solo si lo das |
| Qué compraste y cuándo | Darte acceso y llevar nuestros registros | Normal |
| Confirmación de pago de PayPal | Confirmar que el pedido está pagado | Solo el ID de transacción |
| Dirección IP | Prevención de fraude | **Con hash — no podemos recuperar la original** |

**Cuando visitas:** registros de servidor estándar (página, hora, región aproximada), guardados poco
tiempo y usados solo para que el sitio funcione y esté seguro.

### 2. Qué nunca recopilamos

- **Números de tarjeta, códigos de seguridad ni datos bancarios.** El pago ocurre completamente
  dentro de PayPal. Esos datos nunca nos llegan: ni cifrados, ni un momento, ni nunca.
- **Tu dirección postal ni tu teléfono.** Entregamos a un buzón de correo, así que no los
  necesitamos.
- **Contraseñas**, porque no te pedimos crear cuenta.

### 3. Por qué guardamos tan poco

Cada dato personal que tenemos es algo que debemos proteger. La forma más confiable de mantener tu
información a salvo es no recopilarla. Así que tomamos lo necesario para entregarte tu compra, y
dejamos el resto.

### 4. Para qué lo usamos

Solo para esto:

- Entregarte tu compra y tus enlaces de descarga
- Enviarte tu recibo
- Responderte cuando nos escribes
- Prevenir fraude y abuso
- Cumplir nuestras obligaciones contables
- Avisarte de lanzamientos nuevos — **solo si lo pediste**

Sin perfilado. Sin rastreadores publicitarios. Nada más.

### 5. Quién más lo ve

Una lista corta:

| Quién | Qué recibe | Para qué |
|---|---|---|
| **PayPal** | Los datos de pago que tú les das directamente | Procesar tu pago |
| **Nuestro proveedor de correo** | Tu dirección de correo | Entregarte tus archivos |
| **Nuestro proveedor de hosting** | Datos alojados en nuestros servidores | Operar el sitio |
| Autoridades fiscales o judiciales | Solo lo que la ley exija | Obligación legal |

**Nunca vendemos, alquilamos ni intercambiamos tu información personal.** Ni a anunciantes, ni a
intermediarios de datos, ni a nadie.

### 6. Cómo la protegemos

- Tu correo y tu nombre están **cifrados** en nuestra base de datos: si alguna vez la robaran, esos
  campos no serían legibles
- Las claves de cifrado se guardan aparte de los datos, y nunca junto a nuestras copias de seguridad
- Todo el sitio funciona sobre HTTPS
- El acceso de administración requiere autenticación de dos factores
- Todo acceso a información de clientes queda registrado
- Tus archivos comprados se guardan en almacenamiento privado, alcanzables solo con tus propios
  enlaces

### 7. Cuánto tiempo la guardamos

| Qué | Cuánto |
|---|---|
| Registros de pedidos y pagos | 7 años por contabilidad, luego se anonimizan |
| Tu correo electrónico | Hasta que pidas eliminarlo, o hasta la anonimización |
| Actividad de descargas | 1 año |
| Lista de correo | Hasta que te des de baja |
| Registros de servidor | 30 días |

### 8. Tus derechos

Estés donde estés, con nosotros aplican:

- **Ver** qué tenemos sobre ti
- **Corregir** lo que esté mal
- **Eliminar** tus datos: conservamos solo el mínimo contable, sin datos identificativos
- **Llevarte una copia** en formato portable
- **Darte de baja** del correo con un clic en cualquier mensaje
- **Oponerte** a cómo usamos tus datos

Escribe a [EMAIL_SOPORTE]. Respondemos en 30 días, normalmente mucho antes, y nunca cobramos por
esto.

**Una nota práctica:** como tu correo está cifrado, localizamos tus registros buscando la dirección
exacta. Escribirla tal como la usaste al comprar nos ayuda a encontrarte rápido.

### 9. Cookies

Las mínimas posibles:

| Cookie | Qué hace | Cuánto dura |
|---|---|---|
| Sesión | Mantiene tu carrito funcionando | Hasta que cierres el navegador |
| `cart_count` | Muestra el número en el icono del carrito | 30 días |
| Token de seguridad | Protege contra solicitudes falsificadas | La sesión |

**Sin cookies publicitarias. Sin rastreadores de terceros. Nada que te siga por la web.**

PayPal pone sus propias cookies cuando pagas, cubiertas por la política de privacidad de PayPal.

### 10. Menores

Nuestra tienda está pensada para mayores de 16 años, y no recopilamos información de menores a
sabiendas. Si crees que un menor nos ha dado información, escríbenos y la eliminamos.

### 11. Dónde viven tus datos

Nuestros servidores y proveedores pueden estar en un país distinto al tuyo. Cuando eso pasa,
trabajamos con proveedores que aplican las garantías adecuadas.

### 12. Cambios

Si actualizamos esta política, cambiamos la fecha de arriba y anunciamos en el sitio cualquier
cambio importante.

### 13. Contacto

[EMAIL_SOPORTE] · [ENTIDAD_LEGAL], [PAIS]

---

## 4. Reembolsos (página independiente)

> **Reembolsos**
> Última actualización: [FECHA_VIGENCIA] · Versión 1.0

### Todas las ventas son definitivas

Todo lo que vendemos es digital, así que todas las ventas son definitivas.

Al comprar, los archivos completos son tuyos de inmediato y para siempre. No hay nada que devolver,
ni forma de deshacer una entrega que ya llegó. Al completar tu compra confirmas que quieres tus
archivos enseguida, y que entiendes que después no pueden cancelarse ni devolverse.

### Cuándo lo resolvemos

Arreglamos los problemas reales. Escribe a [EMAIL_SOPORTE] si:

✓ Te cobraron más de una vez
✓ Tus archivos no abren, o llegaron incompletos
✓ Lo que recibiste no corresponde con lo que mostraba la página
✓ Tus enlaces dejaron de funcionar y no podemos repararlos
✓ Pagaste y no llegó nada

Archivos de reemplazo, enlaces nuevos o reembolso: lo que realmente lo solucione.

### Lo que no podemos reembolsar

Estas cosas quedan fuera de nuestras manos una vez que los archivos son tuyos:

- Un cambio de parecer
- Un tamaño, proporción o formato que resultó no ser el que querías
- Cómo salió una impresión en casa o en una imprenta
- Encontrar algo parecido en otro lado

### Antes de comprar

Cada página de producto muestra imágenes de vista previa, la lista completa de archivos incluidos,
las medidas y proporciones, y cómo está hecho el arte.

Si algo no queda claro, pregúntanos. Preferimos mucho más responder una duda que dejarte con algo
que no era para ti.

### Si algo falla con un pago

Escríbenos antes de abrir una disputa en PayPal. Casi siempre lo resolvemos más rápido directamente,
y nos gustaría tener la oportunidad.

### Contacto

[EMAIL_SOPORTE] · Respondemos en [PLAZO_RESPUESTA].

---

## 5. Cómo hacemos nuestro arte (página independiente, enlazada desde cada producto)

> **Cómo hacemos nuestro arte**

### Trabajamos con IA. Esto es lo que significa.

Cada pieza de nuestra tienda empieza con herramientas de generación de imágenes con IA. Lo decimos
abiertamente, porque es parte de cómo trabajamos y no algo que guardar.

Pero un prompt es donde empieza una pieza, no donde termina.

### Nuestro proceso

**Idea** — Un ambiente, una paleta, un espacio. Decidimos qué queremos hacer antes de hacer nada.

**Generación** — Generamos, miramos, ajustamos y volvemos a generar. Una sola pieza puede llevar
decenas de intentos antes de que la composición funcione.

**Selección** — Revisamos todo y nos quedamos con muy poco. La mayoría de lo que generamos nunca
sale de nuestro disco.

**Refinamiento** — Lo que sobrevive se edita, se corrige el color y se escala.

**Preparación** — Cada archivo se prepara para impresión a 300 DPI, en varias medidas y proporciones
estándar, para que funcione con el marco que ya tienes.

**Curaduría** — Las piezas que se acompañan se convierten en sets y colecciones pensadas para verse
juntas en una pared.

### Qué ponemos nosotros

Cualquiera puede escribir un prompt. El trabajo está en todo lo que viene después:

**Criterio** — saber cuál de cincuenta resultados vale la pena

**Oficio** — la edición, el escalado y el trabajo de color que hacen que un archivo imprima bien

**Preparación** — resolución correcta, medidas de marcos reales, varias proporciones listas

**Curaduría** — piezas elegidas para convivir

Esa parte es genuinamente nuestra, y es lo que estás pagando.

### Preguntas que nos hacen

**¿Mi arte es original?**
Cada archivo se genera de forma única para nuestra colección, y nunca partimos de obras existentes
ni las copiamos. Una aclaración honesta: las herramientas de IA pueden dar resultados parecidos a
partir de ideas parecidas, así que no podemos prometer que no exista algo similar en algún lado.

**¿Soy dueño del copyright?**
Recibes una licencia para usar los archivos de forma personal — ver nuestros [Términos de Venta]. El
estatus de copyright de las imágenes generadas con IA todavía está desarrollándose y cambia según el
país, y por eso vendemos una licencia de uso en vez de afirmar que cedemos una propiedad.

**¿Puedo vender impresiones de esto?**
No con la licencia estándar de uso personal, pero hay licencias extendidas para muchas piezas.
Escríbenos.

**¿Se verá bien impreso?**
Nuestros archivos son de 300 DPI en las medidas indicadas, que es resolución estándar de impresión.
El resto depende de tu impresora, tu papel y tu imprenta. Una prueba pequeña primero siempre vale la
pena.

**¿Por qué es tan accesible?**
Nuestro proceso nos deja trabajar en volumen sin recortar en preparación. Preferimos que una pieza
que te encanta termine en tu pared y no fuera de tu alcance.

### ¿Todavía lo estás pensando?

Hay quien prefiere obra hecha enteramente a mano, y es algo muy legítimo de querer.

Si tienes dudas de si nuestras piezas encajan en tu espacio, escríbenos a [EMAIL_SOPORTE]. Con gusto
te ayudamos a decidir, en cualquier dirección.

---

## 6. Archivo de licencia (incluido en cada descarga)

Se guarda como `LICENCIA.txt` y se incluye en cada paquete entregado en español.

```
═══════════════════════════════════════════════════════════
  [NOMBRE_TIENDA] — LICENCIA DE USO PERSONAL
═══════════════════════════════════════════════════════════

  Pedido:    [NUMERO_PEDIDO]
  Fecha:     [FECHA_PEDIDO]
  Producto:  [TITULO_PRODUCTO]
  Licencia:  Uso personal

───────────────────────────────────────────────────────────
  Gracias — esperamos que se vea preciosa en tu pared.
───────────────────────────────────────────────────────────

SOBRE ESTA OBRA

  Creada con herramientas de generación de imágenes con IA,
  y luego seleccionada, refinada y preparada para impresión
  por [NOMBRE_TIENDA].

  La historia completa: [SITIO_WEB]/es/como-hacemos-nuestro-arte

───────────────────────────────────────────────────────────

PUEDES

  ✓ Imprimirla para tu propio espacio
  ✓ Imprimir tantas copias como quieras, para ti
  ✓ Imprimir en casa o en cualquier imprenta
  ✓ Redimensionar o recortar para tu marco
  ✓ Regalar una copia impresa

POR FAVOR, NO

  ✗ Compartas ni revendas los archivos digitales
  ✗ Los subas a ningún sitio en línea
  ✗ Vendas impresiones o productos hechos con ellos
  ✗ Los uses comercialmente ni en publicidad
  ✗ Presentes la obra como creación propia

───────────────────────────────────────────────────────────

NOTAS DE IMPRESIÓN

  Resolución:  300 DPI en las medidas indicadas
  Color:       sRGB
  Consejo:     Una prueba pequeña primero siempre vale
               la pena. Pantalla y papel manejan el
               color de forma distinta.

───────────────────────────────────────────────────────────

  ¿Licencia comercial?       [EMAIL_SOPORTE]
  ¿Problema con tus archivos? [EMAIL_SOPORTE]
  ¿Perdiste tus enlaces?      [SITIO_WEB]/es/pedidos

  Términos completos: [SITIO_WEB]/es/terminos

═══════════════════════════════════════════════════════════
```

---

## 7. Textos cortos usados en todo el sitio

### Casilla del checkout

```
☐  Acepto los Términos de Venta y la Política de Privacidad, y entiendo
   que compro archivos que llegan de inmediato y no tienen devolución.
```

> 📋 **Para ti, no para el cliente.** Corta como es, esta casilla lleva la renuncia al derecho de
> desistimiento de la UE/Reino Unido descrita en §2.8. Cambia el tono libremente, pero los dos
> elementos — *entrega inmediata* y *sin devolución* — tienen que sobrevivir a cualquier
> reescritura.

### Casilla del checkout — productos gratuitos

```
☐  Acepto los Términos de Venta y la Política de Privacidad.
```

### Consentimiento de marketing (separado, opcional, nunca premarcado)

```
☐  Avísame cuando haya arte nuevo. Máximo dos veces al mes, y un clic
   para dejar de recibirlo.
```

### Página de producto — distintivo de IA

```
✦ Hecho con IA, curado y preparado por nosotros · Cómo trabajamos →
```

### Página de producto — nota de entrega

```
⬇  Descarga inmediata · 300 DPI · Varias medidas incluidas
   Tus archivos llegan justo después del pago. Los productos digitales
   son venta final.
```

### Pie de página

```
Arte hecho con IA, seleccionado y preparado por [NOMBRE_TIENDA].
Términos · Privacidad · Reembolsos · Cómo trabajamos
```

### Correo de confirmación — línea de pie

```
Tus archivos tienen licencia de uso personal. Por favor, guárdalos para
ti: es lo que nos permite mantener los precios donde están.
Términos completos: [SITIO_WEB]/es/terminos
```

---

## 8. Checklist de construcción

### Páginas a crear

- [ ] `/es/terminos` — Términos de Venta (versionado)
- [ ] `/es/privacidad` — Política de Privacidad (versionado)
- [ ] `/es/reembolsos` — Reembolsos (versionado)
- [ ] `/es/como-hacemos-nuestro-arte` — página de proceso
- [ ] `/es/licencia` — términos de licencia completos

Todas con `index, follow`, y con `hreflang` recíproco hacia su equivalente en inglés (plan §4.7.4).

### Requisitos técnicos

- [ ] Documentos legales como **registros versionados**, nunca sobrescritos al editar (plan §7.7.2)
- [ ] Cada versión contiene **ambos idiomas**, para que una actualización no deje un idioma desfasado
      (plan §4.7.1)
- [ ] Cada página muestra su número de versión y fecha de vigencia
- [ ] `terms_version` y `locale` registrados en cada pedido
- [ ] La vista de pedido en el admin enlaza a la versión **y el idioma** exactos que aceptó el cliente
- [ ] Casilla obligatoria en el checkout, desmarcada por defecto, **validada en el servidor**
- [ ] Los enlaces abren en pestaña nueva para no perder el carrito
- [ ] El consentimiento de marketing es una casilla aparte y opcional
- [ ] `LICENCIA.txt` generado por pedido en el idioma del pedido, incluido en cada descarga
- [ ] Distintivo de IA en tarjetas y páginas de producto donde `is_ai_generated`
- [ ] Enlaces del pie presentes en todas las páginas
- [ ] Las notas 📋 de este archivo se **eliminan** antes de publicar cualquiera de estos textos

### Antes del lanzamiento

- [ ] Todos los `[MARCADORES]` reemplazados, en ambos idiomas
- [ ] País de establecimiento decidido (plan §24.2) — define la jurisdicción
- [ ] Revisión legal de los tres puntos marcados al inicio de este archivo
- [ ] **Revisión legal sobre la versión en español específicamente**, no solo sobre la inglesa: no es
      una traducción literal, y las dos versiones deben decir lo mismo jurídicamente
- [ ] Correo de soporte activo y atendido
- [ ] Plazo de respuesta ajustado a algo que puedas cumplir
- [ ] Afirmaciones de impresión (300 DPI, medidas) verificadas en todos los productos
- [ ] Ambas versiones revisadas por un hablante nativo de cada idioma
