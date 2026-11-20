# Requisitos de "Climate Change Analyzer" (CCA)

Fuente única: ERS v1.0 (código SI_PHP_CCA_2026, 21/10/2026, Vicente Torres, asignatura Sistemas de Información). Se mantienen los identificadores originales (CU, CAR, RQ). Los requisitos sin identificador en el ERS llevan uno propio (GEN, TEC, UI, ENT) para poder referenciarlos.

Las marcas ⚠️ señalan puntos ambiguos o contradictorios; están recogidos al final en la sección 8.

---

## 1. Visión general (secciones 1 y 2)

- **GEN-01** Aplicación web para un número indeterminado de usuarios (alcance, 1.2; CAR04).
- **GEN-02** El sistema actúa de forma independiente: no interactúa con ningún otro sistema, salvo los navegadores web de los usuarios (sección 2).
- **GEN-03** El ERS sirve de base para las pruebas funcionales de aceptación y la evaluación final del cliente (el profesor) (1.1).
- **GEN-04** Todo lo no contemplado en los requisitos debe consultarse con el cliente (3.3).
- **GEN-05** Actores: **Usuario** (no registrado), **Usuario registrado** y **Administrador** (CAR05, diagrama de casos de uso).

---

## 2. Requisitos técnicos y de interfaz del sistema (2.1 – 2.4)

### 2.1 Tecnologías
- **TEC-01** Cliente: únicamente código HTML y cierto código JavaScript (2.1).
- **TEC-02** Se pueden usar plantillas CSS de forma opcional (2.1).
- **TEC-03** La parte de servidor se realiza obligatoriamente en PHP (2.1, 2.4, 3.3).
- **TEC-04** Las páginas dinámicas y la lógica de programa se construyen con páginas PHP (3.3).
- **TEC-05** El almacenamiento persistente es un Sistema Gestor de Bases de Datos (3.3; 2.4 "Almacenamiento persistente").
- **TEC-06** El cliente debe soportar JavaScript (2.4).

### 2.2 Interfaz de usuario
- **UI-01** Interfaz web basada en una **plantilla Bootstrap**.
- **UI-02** Debe poder usarse en pantallas con resolución mínima 1920×1080 ⚠️ (frase incompleta en el original).
- **UI-03** Aplicar criterios estandarizados de usabilidad.
- **UI-04** Visualización correcta en **Microsoft Edge, Mozilla Firefox y Google Chrome**.
- **UI-05** Safari: opcional en 2.2; en 2.4 aparece en la lista de navegadores ⚠️.

### 2.3 Interfaz hardware / despliegue
- **TEC-07** Arquitectura en tres capas: módulo de presentación (Servidor Web), lógica de negocio/APP (Servidor de aplicaciones) y almacenamiento persistente (Servidor 3). Los clientes (navegadores) solo se comunican con el módulo de presentación, que llama a la APP, que accede al almacenamiento. Las tres capas pueden estar en servidores distintos o en el mismo equipo.

---

## 3. Casos de uso (2.5)

Flujo del diagrama: tras CU7 (seleccionar ciudad) el usuario registrado accede a CU8 (estaciones) y CU9 (estudio general).

### Usuario (no registrado)
- **CU1 Registrarse**
  - Formulario de registro en la interfaz.
  - El **nombre de usuario** es único y es el nick/alias dentro de la aplicación.
  - Se almacenan: nombre, apellidos, fecha de nacimiento, correo electrónico y contraseña ⚠️ (ver RQ15).
  - La contraseña se introduce **dos veces** para comprobar que no hay error.
  - La contraseña **no debe verse** en pantalla al escribirla.

### Usuario registrado
- **CU2 Loguearse**
  - Solo usuarios ya registrados, con pareja válida usuario + contraseña.
  - **No se pueden ver datos meteorológicos sin haber hecho login.**
  - Tras **5 minutos sin interacción** se cancela la autenticación y debe repetirse.
- **CU3 Ver perfil**
  - Sección personal con los datos del perfil, que aparecen **ya cargados en un formulario editable**.
- **CU4 Modificar perfil**
  - Puede modificar sus datos.
  - Subsección para **cambiar la contraseña sin tocar el resto de datos**.
- **CU5 Darse de baja**
  - Sección específica para ello.
  - El sistema **pide la contraseña** para verificar que es el usuario quien lo solicita.
- **CU6 Cerrar sesión**
  - Disponible para usuario registrado **y administrador**, tras haber hecho login (CU2).
- **CU7 Seleccionar ciudad**
  - Primer paso de cualquier estudio meteorológico: elegir ciudad entre las disponibles.
- **CU8 Estudio de estaciones meteorológicas**
  - Estudio del comportamiento climático a lo largo de la historia, por estación (primavera, verano, otoño, invierno), con temperaturas máximas, medias, mínimas, precipitación… ⚠️ (el "…" no concreta más variables ni formato).
  - **CU8a Invierno**: datos de diciembre, enero y febrero.
  - **CU8b Verano**: datos de junio, julio y agosto.
  - **CU8c Primavera**: datos de marzo, abril y mayo.
  - **CU8d Otoño**: datos de septiembre, octubre y noviembre (el original dice "verano" por error).
- **CU9 Estudio general**
  - Estudio de **una sola** variable climática, elegida por el usuario, a lo largo de toda la historia.
  - **CU9a Temperatura máxima**: informe del nº de días al año con temperatura **máxima ≥ X °C** en todo el periodo histórico. El usuario indica X. Se muestra un **gráfico**.
  - **CU9b Temperatura mínima**: nº de días al año con temperatura **mínima ≤ X °C**. El usuario indica X. Gráfico.
  - **CU9c Temperatura media**: nº de días al año con temperatura **media ≥ X °C**. El usuario indica X. Gráfico.
  - **CU9d Noches "tropicales"**: nº de días al año con temperatura **≥ X °C**; el usuario indica X; gráfico ⚠️ (texto idéntico a CU9a, no dice qué variable).
  - **CU9e Precipitaciones**: cantidad de precipitación en todo el periodo histórico, mostrada de **dos formas**: mensual y anual.

### Administrador
- **CU10 Crear administrador**: sección para crear un nuevo administrador. Solo administradores.
- **CU11 Eliminar administrador**: sección con **lista de usuarios** donde el administrador selecciona a quién eliminar. Solo administradores ⚠️ (¿lista de administradores o de usuarios?).
- **CU12 Eliminar usuarios**: lista de usuarios; el administrador selecciona el que desea eliminar. Solo administradores.
- CU6 (cerrar sesión) también aplica al administrador.

---

## 4. Características del producto (sección 3)

### 3.1 Características
- **CAR01** Acceso identificándose con nombre de usuario (alias/nick) y contraseña.
- **CAR02** Los nombres de usuario son únicos.
- **CAR03** Contraseña de **mínimo 6 caracteres** (durante la fase de pruebas pueden ser de 1 carácter).
- **CAR04** Gestionar un número indeterminado de usuarios.
- **CAR05** Tres tipos de usuario: Usuario, Usuario registrado y Administrador.
- **CAR06** Modo de administración: el administrador desempeña sus funciones mediante páginas web específicas.
- **CAR07** Los administradores pueden eliminar a un usuario **y toda su información asociada**.
- **CAR08** Cada usuario solo accede a la información que le pertenece.
- **CAR09** Los datos meteorológicos se almacenan en una **base de datos**.
- **CAR10** Los datos meteorológicos se obtienen del proyecto de investigación realizado en la asignatura Sistemas de Información (EUPT).
- **CAR11** La BD debe contener **datos reales de entre 3 y 5 ciudades**.

### 3.2 Características del usuario
- Usuario: nivel técnico básico. Administrador: nivel técnico medio ⚠️ (aquí se habla de dos tipos, CAR05 dice tres).

### 3.3 Restricciones
- Páginas dinámicas y lógica de programa en PHP.
- Almacenamiento persistente con un SGBD.
- Lo no contemplado en los requisitos se consulta con el cliente.
- **El acceso al login del administrador es distinto al del resto de usuarios.**

---

## 5. Requisitos específicos (sección 4)

### 4.1 Interfaz
- **RQ1** Navegación sencilla y eficiente, con criterios estándar de usabilidad.
- **RQ2** Estructura de páginas homogénea e interfaz agradable.
- **RQ3** El usuario sabe en todo momento en qué sección del sitio se encuentra.
- **RQ4** Todas las páginas tienen un enlace a la página de inicio.
- **RQ5** Con perfil registrado, desde cualquier página debe poder abandonarse la sesión.
- **RQ6** Estructura balanceada, **máximo 4 niveles de profundidad**.
- **RQ7** En toda tabla, la primera fila contiene los encabezados de cada columna.
- **RQ8** Visualización correcta en Edge, Firefox y Chrome.
- **RQ9** No se tramita ningún formulario sin comprobar que, al menos, está rellenado.

### 4.2 Registro de usuarios
- **RQ10** El contenido no es público: solo accede a él gente registrada.
- **RQ11** Al ejecutar la aplicación debe existir un **administrador general por defecto**: usuario `pepe`, contraseña `pepe`.
- **RQ12** Los administradores pueden eliminar a un usuario y toda su información asociada.
- **RQ13** Al eliminar un usuario se elimina toda la información asociada.
- **RQ14** El identificador de usuario es único en todo el sistema.
- **RQ15** Datos de un usuario: nick, contraseña, nombre, apellidos, **edad**, **teléfono** y e-mail ⚠️.
- **RQ16** El "nombre de usuario" es único en todo el sistema.

### 4.5 Otros requisitos (el ERS salta de 4.2 a 4.5)
- **RQ17** Panel de administración en el fichero **`administración.php`**, accesible solo a administradores.
- **RQ18** Cada tipo de usuario solo accede a la información a la que tiene acceso.
- **RQ19** En el perfil, la información del usuario se muestra en un formulario.
- **RQ20** La contraseña se modifica con **otro formulario distinto** al del perfil.
- **RQ21** Para cambiar la contraseña: pedir la actual, la nueva y la confirmación de la nueva.
- **RQ22** Control de formularios **exhaustivo**.
- **RQ23** Todas las tablas de la BD tienen un campo **ID**.
- **RQ24** Un administrador puede eliminar a un usuario de la aplicación.
- **RQ25** Cada página tiene **header, body y footer** claramente diferenciados.
- **RQ26** Es imprescindible **separar la interfaz gráfica de la lógica de control**.
- **RQ27** Por legislación, los datos de usuarios registrados y administradores deben estar encriptados (hashing en contraseñas) ⚠️.
- **RQ28** Usar la librería **PDO** para la conexión con la BD.

---

## 6. Entrega (sección 5)

- **ENT-01** Entregar el proyecto comprimido en **ZIP o RAR**.
- **ENT-02** La práctica debe **defenderse ante el profesor**; la no defendida no se califica.
- **ENT-03** La copia implica la pérdida de la evaluación continua para quienes tengan la misma práctica.
- **ENT-04** Para aprobar es imprescindible tener **separada la lógica de control de la interfaz de usuario**; no respetarlo supone suspender la práctica (refuerza RQ26).

---

## 7. Definiciones de dominio útiles (1.3)

- **Sesión**: autenticación del usuario registrado (usuario + contraseña) que permite identificar cada petición y generar contenido individualizado.
- **Primavera**: 1 mar – 31 may. **Verano**: 1 jun – 31 ago. **Otoño**: 1 sep – 30 nov. **Invierno**: 1 dic – 28/29 feb.
- **Precipitación**: agua que cae (lluvia, nieve, granizo), en **mm**.
- **Temperatura máxima / mínima**: la más alta / más baja del día, en **°C**.
- **Temperatura media**: (máxima + mínima) / 2, en **°C**.
- **TBD**: valor o decisión aún por definir.

---

## 8. Dudas, contradicciones y erratas del ERS

1. **CU9d (noches tropicales)**: el texto es idéntico a CU9a ("temperatura ≥ X"). Una noche tropical suele ser mínima ≥ 20 °C; ¿se usa la temperatura mínima?, ¿X es libre o fijo?
2. **Datos de usuario**: CU1 pide nombre, apellidos, fecha de nacimiento, e-mail y contraseña; RQ15 pide edad y teléfono (y no fecha de nacimiento). ¿Qué campos finales?
3. **Tipos de usuario**: CAR05 dice tres (Usuario, Usuario registrado, Administrador); 3.2 solo describe dos.
4. **CU11**: "Eliminar administrador" muestra una lista de usuarios; ¿debe ser lista de administradores? ¿Puede eliminarse al admin por defecto `pepe`?
5. **CU8**: el "…" tras temperaturas y precipitación no define el resto de datos ni cómo se presentan (¿gráficos, tablas?).
6. **Safari**: opcional en 2.2, listado sin matiz en 2.4 y ausente en RQ8.
7. **Resolución 1920×1080**: la frase de 2.2 está incompleta.
8. **RQ27**: "todos los datos" encriptados, pero el paréntesis habla solo de hashing de contraseñas.
9. **Textos heredados de otro proyecto**: alcance habla de un "foro" y la definición de sesión de "podcasts"; no parecen aplicables a CCA.
10. **Numeración**: faltan las secciones 4.3 y 4.4; CU8d dice "verano" en vez de "otoño".
11. **CAR10**: los datos vienen del proyecto de investigación de la asignatura, sin concretar formato ni cómo se entregan.
12. **CU5**: no dice si la baja borra también la información asociada (RQ13 lo exige para el borrado por un administrador).
