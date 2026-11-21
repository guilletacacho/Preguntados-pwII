# Autoevaluación del proyecto — Trabajo Práctico Final PW2

Revisión del código del proyecto contra los 35 criterios de la grilla compartida. “Completa” significa que encontré implementación correspondiente en el repositorio; no reemplaza una prueba funcional en producción. Los ítems parciales aparecen en **Faltantes**, con una nota de lo que ya existe y lo que resta.

## Completas

### 1. Usuarios, autenticación y perfil

- **1.1 Registro completo de usuario.** Formulario, validaciones en servidor, disponibilidad del nombre y persistencia.
- **1.3 Validación de cuenta por correo electrónico.** Token de verificación con vencimiento y activación de cuenta.
- **1.4 Inicio de sesión (Login).** Autenticación, control de cuenta verificada y cierre de sesión.
- **1.5 Perfil público y privado del jugador (con código QR).** Perfil visible a usuarios autenticados, edición del propio perfil y generación/descarga de QR.

### 2. Lobby y ranking

- **2.2 Navegación principal del Lobby.** Menú con acceso a juego, rankings, preguntas frecuentes, perfil y opciones relacionadas.
- **2.3 Historial de partidas jugadas.** El perfil muestra las últimas partidas con estado, fecha y puntaje.

### 3. Mecánica de juego y dificultad adaptativa

- **3.1 Selección de categoría por pregunta (ruleta).** Ruleta integrada al flujo de partida.
- **3.2 Presentación de preguntas tipo ABCD.** Se muestran cuatro opciones identificadas con letras.
- **3.3 Identificación visual por color de categoría.** Categorías y componentes de ruleta usan colores/recursos propios.
- **3.4 Sistema de puntuación y corte inmediato por error.** El servidor calcula puntos; una respuesta incorrecta finaliza la partida.
- **3.6 Cálculo y entrega por dificultad dinámica.** El nivel del jugador se calcula con su historial y la dificultad influye en preguntas y puntaje.
- **3.8 Sugerir pregunta nueva desde el Lobby.** Hay acceso desde preguntas frecuentes al formulario de sugerencias.

### 4. Anti-cheat y control de accesos

- **4.1 Persistencia ante recarga de página (F5).** Partida, turno, resultado y puntaje se conservan entre solicitudes mediante sesión y base de datos.
- **4.2 Control estricto de tiempo en Backend.** El servidor registra el inicio del turno y valida vencimiento; el contador del navegador no decide el resultado.
- **4.3 Ocultamiento de la respuesta correcta al cliente.** La vista recibe opciones y tokens opacos; la respuesta correcta se comprueba en el servidor.
- **4.4 Integridad de turno y validación de pregunta entregada.** Se valida contra el turno guardado en sesión y no se aceptan IDs de pregunta/turno como autoridad del cliente.
- **4.5 Una única partida en curso por usuario (concurrencia).** Hay restricción única en la base y bloqueo de sesión de usuario.
- **4.6 Protección contra reenvío de respuestas (Replay Attack).** El turno tiene unicidad en base; un reenvío no suma puntos otra vez.
- **4.7 Control de flujo y bloqueo de navegación hacia atrás.** Hay manejo de historial/caché y validaciones del estado de partida en servidor.

### 5. Rol Editor

- **5.2 Gestión y moderación de preguntas reportadas.** El panel permite filtrar, revisar, corregir, resolver o descartar reportes.
- **5.3 Moderación y aprobación de preguntas sugeridas.** El moderador puede editar, publicar o rechazar propuestas.

### 7. UX, interfaz y arquitectura técnica

- **7.3 Funcionalidad asíncrona con AJAX.** Se usa `fetch` para disponibilidad de usuario, geocodificación, presencia y acciones de ruleta.
- **7.4 Arquitectura MVC en PHP y base de datos.** La aplicación separa controladores, modelos y vistas, y persiste en MySQL.
- **7.5 Control de versiones en Git.** El proyecto está versionado y tiene un remoto Git configurado.

## Faltantes

### 1. Usuarios, autenticación y perfil

- **1.2 Selección de ubicación en mapa interactivo — Parcial.** El registro obtiene la ubicación del navegador y el perfil muestra un mapa; no encontré selección/ajuste de ubicación haciendo clic o moviendo un marcador en el mapa.

### 2. Lobby y ranking

- **2.1 Cabecera del Lobby con nombre y puntaje.** El Lobby saluda por nombre, pero no muestra el puntaje del jugador en su cabecera.
- **2.4 Ranking global dinámico (con movimiento real de posiciones) — Parcial.** Hay rankings semanal, mensual e histórico calculados desde partidas; no encontré actualización en vivo ni animación/movimiento de posiciones cuando cambia el orden.

### 3. Mecánica de juego y dificultad adaptativa

- **3.5 Control de no repetición de preguntas — Parcial.** Se priorizan preguntas aún no usadas durante la partida, pero si se agotan las disponibles pueden repetirse.
- **3.7 Reportar pregunta inválida sin saltear turno — Parcial.** El reporte existe, pero enviar un reporte finaliza la partida; no permite reportar y continuar el mismo turno/juego.

### 4. Anti-cheat y control de accesos

- **4.8 Control de acceso por roles (RBAC) en URLs y acciones.** Hay verificación de rol en ciertas acciones, pero faltan controles uniformes: por ejemplo, la bandeja de sugerencias y varias operaciones del catálogo solo verifican que exista una sesión. Aplicar la validación de rol en cada ruta protegida.

### 5. Rol Editor

- **5.1 ABM de preguntas y categorías — Parcial.** Hay alta, baja lógica, edición y listado de preguntas; las categorías se consultan para formularios/filtros, pero no encontré ABM de categorías.

### 6. Rol Administrador (reportes y estadísticas)

- **6.1 Indicadores generales de la plataforma — Parcial.** El panel de moderación muestra algunos conteos, pero no encontré un panel administrativo completo; además, no se aplica de forma uniforme un rol `ADMIN` con acceso propio.
- **6.2 Gráficos y métricas de rendimiento y demografía.** No encontré métricas administrativas con gráficos ni análisis demográfico.
- **6.3 Filtros temporales en todos los reportes.** No encontré un conjunto de reportes administrativos con filtros de período aplicables a todos ellos.
- **6.4 Impresión/exportación de reportes.** No encontré opciones de exportación o impresión de reportes del administrador.

### 7. UX, interfaz y arquitectura técnica

- **7.1 Diseño responsive — Parcial.** Hay media queries y ajustes para pantallas pequeñas en varias vistas; falta verificar y cerrar el comportamiento responsive de toda la aplicación en distintos dispositivos.
- **7.2 Animaciones y audio con control de silencio — Parcial.** Hay animaciones CSS/JS, especialmente en la ruleta; no encontré audio ni un control para silenciarlo.
- **7.6 Despliegue funcional en servidor en línea (InfinityFree) — Parcial.** Existe un workflow de GitHub Actions preparado para desplegar por FTP a InfinityFree, pero el código del repositorio no permite confirmar que las credenciales estén configuradas ni que el despliegue termine correctamente y el sitio esté operativo.

## Prioridad sugerida para cerrar faltantes

1. Corregir RBAC en todas las rutas y acciones de moderación/administración.
2. Completar el panel de administrador: rol, indicadores reales, gráficos, filtros y exportación.
3. Mostrar el puntaje en el Lobby y resolver el reporte de pregunta sin terminar la partida.
4. Completar el ABM de categorías y el selector de ubicación sobre el mapa.
5. Decidir el comportamiento esperado al agotar preguntas y completar el ranking con actualización/movimiento real.
6. Revisar responsive en móvil, agregar audio con control de silencio si lo pide la grilla y confirmar el despliegue online.

## Evidencia revisada

Controladores, modelos, vistas, scripts, estilos, esquemas SQL, workflow de despliegue y documentación del proyecto. No ejecuté pruebas ni confirmé el estado del sitio publicado; los puntos marcados como parciales reflejan esa falta de evidencia o funcionalidades que el código todavía no completa.
