# Presupuesto – Sitio web VOLARA (gestión de reservas de pasajes de avión)

Método: Análisis de Puntos de Función (IFPUG) para medir el tamaño, más estimación por horas de esfuerzo y porcentajes de testing, gestión, contingencia y ganancia, según el material de la cátedra (Díaz / Butti, UTN-FRRO).

Los importes están en pesos argentinos y **no incluyen IVA**.

---

## 1. Supuestos del proyecto

| Dato | Valor |
|---|---|
| Equipo | 4 desarrolladores junior / estudiantes |
| Dedicación | 4 horas por día por persona |
| Días hábiles por semana | 5 (supuesto) |
| Duración tomada como base | 3 semanas = 15 días hábiles (la alternativa de 2 semanas está en el punto 7) |
| **Esfuerzo total** | 4 personas × 4 h × 15 días = **240 horas** |
| Tecnología | PHP, MySQL, Bootstrap, repositorio en GitHub (todo sin costo de licencia) |
| Tamaño del código | 47 archivos PHP, unas 15.100 líneas |

---

## 2. Medición del software: puntos de función

### 2.1 Pesos usados (tabla de la cátedra)

| Componente | Simple | Medio | Complejo |
|---|---|---|---|
| Entradas externas (EE) | 3 | 4 | 6 |
| Salidas externas (SE) | 4 | 5 | 7 |
| Consultas externas (CE) | 3 | 4 | 6 |
| Archivos lógicos internos (ALI) | 7 | 10 | 15 |
| Interfaces externas (IE) | 5 | 7 | 10 |

### 2.2 Entradas externas: 24 entradas, 101 puntos

| Módulo | Entrada | Complejidad | Puntos |
|---|---|---|---|
| Cuentas | Registro de usuario o CEO (validaciones, aerolínea, mail de validación) | Compleja | 6 |
| Cuentas | Iniciar sesión | Media | 4 |
| Cuentas | Solicitar recuperación de contraseña | Media | 4 |
| Cuentas | Restablecer contraseña con enlace de un solo uso | Media | 4 |
| Cuentas | Validar cuenta con token | Simple | 3 |
| Cuentas | Reenviar validación | Simple | 3 |
| Cuentas | Modificar perfil | Media | 4 |
| Cuentas | Autorizar o rechazar CEO | Media | 4 |
| Administrador | Alta de aerolínea | Media | 4 |
| Administrador | Modificar aerolínea | Media | 4 |
| Administrador | Eliminar aerolínea | Simple | 3 |
| Administrador | Alta de novedad | Media | 4 |
| Administrador | Modificar novedad | Media | 4 |
| Administrador | Eliminar novedad | Simple | 3 |
| Administrador | Aprobar o denegar promoción | Media | 4 |
| CEO | Alta de vuelo (código autogenerado, validaciones) | Compleja | 6 |
| CEO | Modificar vuelo (regla de 24 hs, bloqueo con reservas) | Compleja | 6 |
| CEO | Eliminar vuelo (baja lógica, control de reservas) | Media | 4 |
| CEO | Alta de promoción | Media | 4 |
| CEO | Modificar promoción | Media | 4 |
| CEO | Eliminar promoción | Simple | 3 |
| Pasajero | Reservar vuelo y elegir hasta 6 asientos | Compleja | 6 |
| Pasajero | Confirmar pago único por compra | Compleja | 6 |
| Pasajero | Cancelar reserva (hasta 72 hs antes) | Media | 4 |
| | **Total EE** | | **101** |

### 2.3 Salidas externas: 7 salidas, 39 puntos

| Salida | Complejidad | Puntos |
|---|---|---|
| Reporte de ventas de la aerolínea (CEO, con totales y filtro por fechas) | Compleja | 7 |
| Reporte de ocupación de vuelos (CEO, con porcentajes) | Compleja | 7 |
| Reporte de ventas (administrador) | Compleja | 7 |
| Reporte de vuelos (administrador) | Media | 5 |
| Reporte de usuarios (administrador) | Media | 5 |
| Mail de validación de cuenta | Simple | 4 |
| Mail de recuperación de contraseña | Simple | 4 |
| **Total SE** | | **39** |

### 2.4 Consultas externas: 16 consultas, 63 puntos

| Consulta | Complejidad | Puntos |
|---|---|---|
| Buscar vuelos con filtros y paginación (público) | Compleja | 6 |
| Mapa de asientos y detalle del vuelo | Compleja | 6 |
| Inicio con novedades | Media | 4 |
| Mis reservas (filtro por estado, pagos pendientes, paginación) | Media | 4 |
| Historial de reservas | Media | 4 |
| Listado de vuelos del CEO (paginado) | Media | 4 |
| Listado de promociones del CEO | Media | 4 |
| Listado de aerolíneas (administrador) | Media | 4 |
| Listado de novedades (administrador) | Media | 4 |
| Autorizaciones de CEO pendientes | Media | 4 |
| Promociones pendientes de aprobar | Media | 4 |
| Listado de aerolíneas (público) | Simple | 3 |
| Novedades vigentes (público) | Simple | 3 |
| Promociones vigentes (público) | Simple | 3 |
| Mapa de sitio (según tipo de usuario) | Simple | 3 |
| Ver perfil | Simple | 3 |
| **Total CE** | | **63** |

### 2.5 Archivos lógicos internos: 6 tablas, 51 puntos

| Tabla | Complejidad | Puntos |
|---|---|---|
| usuarios (estados, tokens, rol) | Media | 10 |
| vuelos (baja lógica, asientos) | Media | 10 |
| reservas (asiento, compra, promoción) | Media | 10 |
| aerolineas | Simple | 7 |
| promociones | Simple | 7 |
| novedades | Simple | 7 |
| **Total ALI** | | **51** |

### 2.6 Interfaces externas: 1 interfaz, 5 puntos

| Interfaz | Complejidad | Puntos |
|---|---|---|
| Servicio de correo SMTP (PHPMailer) | Simple | 5 |

### 2.7 Puntos de función no ajustados

| Componente | Puntos |
|---|---|
| EE | 101 |
| SE | 39 |
| CE | 63 |
| ALI | 51 |
| IE | 5 |
| **PFNA** | **259** |

### 2.8 Factores de complejidad general (GSC, escala de 0 a 5)

| # | Factor | Valor | Motivo |
|---|---|---|---|
| 1 | Comunicación de datos | 3 | Web (HTTP) y envío de correo (SMTP) |
| 2 | Funciones distribuidas | 1 | Una sola aplicación |
| 3 | Rendimiento | 2 | Sin requisitos exigentes |
| 4 | Configuración de hardware muy usada | 1 | Servidor estándar |
| 5 | Tasa de transacciones | 2 | Volumen bajo o medio |
| 6 | Entrada de datos en línea | 5 | Todo se carga en línea |
| 7 | Eficiencia del usuario final | 4 | Diseño adaptable, accesibilidad, paginación |
| 8 | Actualización en línea | 4 | ABM en línea |
| 9 | Procesamiento complejo | 3 | Reglas de negocio (72 hs, 24 hs, asientos, promociones) |
| 10 | Reusabilidad | 2 | Funciones y plantillas comunes |
| 11 | Facilidad de instalación | 2 | Requiere importar esquema y datos |
| 12 | Facilidad de operación | 2 | Sin automatismos de respaldo |
| 13 | Múltiples sitios | 0 | Una sola instalación |
| 14 | Facilidad de cambio | 3 | Código modular, sin tests automáticos |
| | **Σ GSC** | **34** | |

```
FA   = 0,65 + 0,01 × 34 = 0,99
PFA  = 259 × 0,99       = 256,41 puntos de función
```

---

## 3. Esfuerzo

La cátedra permite estimar por horas de esfuerzo cuando no se conoce la productividad: sobre las horas de programación se suma un 20 % de testing y un 10 % de gestión. Como las 240 horas ya incluyen todo lo trabajado, se reparten con esa misma proporción (100 : 20 : 10).

| Rol | Cálculo | Horas |
|---|---|---|
| Desarrollo | 240 ÷ 1,30 | 184,6 |
| Testing (20 %) | 184,6 × 0,20 | 36,9 |
| Gestión de proyecto (10 %) | 184,6 × 0,10 | 18,5 |
| **Total** | | **240,0** |

---

## 4. Costo del personal

Valores de referencia de la cátedra (mensuales). Una jornada completa es de 20 días × 8 horas = 160 horas por mes.

| Rol | Sueldo mensual | Costo por hora | Horas | Costo |
|---|---|---|---|---|
| Desarrollador | $ 60.000 | $ 375,00 | 184,6 | $ 69.230,77 |
| Tester | $ 35.000 | $ 218,75 | 36,9 | $ 8.076,92 |
| Gestión de proyecto | $ 95.000 | $ 593,75 | 18,5 | $ 10.961,54 |
| **Costo del personal** | | | | **$ 88.269,23** |

> **Importante:** estos sueldos son los valores de ejemplo de la cátedra. Antes de entregar, conviene reemplazarlos por los vigentes del Observatorio de la industria del software de CESSI (https://cessi.org.ar/opssi/) o del Consejo Profesional de Ciencias Informáticas de Córdoba (https://www.cpcipc.org.ar/content/honorarios). No encontré cifras actualizadas de 2026 para citar.

---

## 5. Presupuesto del proyecto

| Concepto | Criterio | Importe |
|---|---|---|
| Costo del personal | Punto 4 | $ 88.269,23 |
| Infraestructura, herramientas e indirectos | 10 % del personal | $ 8.826,92 |
| **Subtotal de costos** | | **$ 97.096,15** |
| Contingencias | 15 % (la cátedra recomienda entre 10 y 30 %) | $ 14.564,43 |
| **Subtotal con contingencias** | | **$ 111.660,58** |
| Margen de ganancia | 20 % (supuesto del equipo) | $ 22.332,12 |
| **PRESUPUESTO TOTAL (sin IVA)** | | **$ 133.992,69** |

Costo por punto de función obtenido: $ 133.992,69 ÷ 256,41 ≈ **$ 522,6 por punto**.

Costo por hora del proyecto: $ 133.992,69 ÷ 240 h ≈ **$ 558,3 por hora**.

Las herramientas (PHP, MySQL, Bootstrap, GitHub, XAMPP) no requieren licencias, por eso el 10 % de infraestructura e indirectos es un valor chico. Hosting y dominio, si el cliente los pide, se cotizan aparte.

---

## 6. Presupuesto desglosado por módulo

Cada componente de las tablas del punto 2 se asignó a su módulo. El importe se reparte según los puntos (el total dividido por 259 da $ 517,35 por punto no ajustado).

| Módulo | EE | SE | CE | ALI | IE | Puntos | % | Importe |
|---|---|---|---|---|---|---|---|---|
| Cuentas de usuario (registro, login, recuperación, perfil, mails) | 32 | 8 | 3 | 10 | 5 | 58 | 22,4 % | $ 30.006 |
| Administración (aerolíneas, novedades, autorizaciones, promociones, reportes) | 26 | 17 | 16 | 14 | 0 | 73 | 28,2 % | $ 37.766 |
| CEO (vuelos, promociones, reportes) | 27 | 14 | 8 | 17 | 0 | 66 | 25,5 % | $ 34.146 |
| Pasajero (reservas, asientos, pago, historial) | 16 | 0 | 14 | 10 | 0 | 40 | 15,4 % | $ 20.694 |
| Sitio público (inicio, búsqueda, listados, mapa de sitio) | 0 | 0 | 22 | 0 | 0 | 22 | 8,5 % | $ 11.382 |
| **Total** | **101** | **39** | **63** | **51** | **5** | **259** | **100 %** | **$ 133.993** |

(Los importes están redondeados al peso, por eso la suma puede diferir en $ 1.)

---
## 7. Sensibilidad y comparación

### 7.1 Si fueron 2 semanas

| Escenario | Horas | Presupuesto total (sin IVA) |
|---|---|---|
| 2 semanas (10 días hábiles) | 160 | $ 89.328 |
| **3 semanas (15 días hábiles)** | **240** | **$ 133.993** |

Con cualquier cantidad de horas, el total es aproximadamente **horas × $ 558,3**, mientras se mantengan las mismas tarifas y porcentajes.

### 7.2 Comparación con el método por puntos de función de la cátedra

La cátedra valúa cada punto en $ 13.500, a partir de un equipo profesional que produce 30 puntos por mes. Con ese valor:

```
256,41 puntos × $ 13.500 = $ 3.461.535
```

La diferencia con el presupuesto por horas (unos 26 veces más) viene de la productividad. La referencia de la cátedra implica unas 16 horas por punto, y este proyecto resultó en menos de 1 hora por punto (240 h ÷ 256 puntos). Hay tres explicaciones posibles, que conviene revisar antes de entregar:

1. Las 240 horas pueden estar subestimadas (no cuentan estudio, reuniones, rehacer trabajo ni correcciones).
2. El conteo de puntos puede ser generoso en algunos componentes.
3. Si se usaron asistentes de IA o plantillas, la productividad no es comparable con la de un equipo tradicional y conviene declararlo.

Por eso este presupuesto se calcula **por horas reales**, y los puntos de función se usan como medida de tamaño.

---

## 8. Riesgos y contingencias

| Riesgo | Probabilidad | Impacto | Respuesta |
|---|---|---|---|
| Cambios de alcance durante el desarrollo | Alta | Alto | Control de cambios: todo pedido nuevo se presupuesta aparte |
| Equipo con poca experiencia (juniors) | Alta | Medio | Contingencia del 15 %, revisión de código entre pares |
| Modelo de datos que cambia (columnas nuevas) | Media | Medio | Script de esquema versionado y avisos al equipo |
| Falta de pruebas automáticas | Media | Medio | Testing manual por rol y validadores W3C y TAW |
| Dependencia del servicio de correo externo | Baja | Medio | Segundo proveedor SMTP como plan alternativo |
| Diferencias entre entornos (rutas, mayúsculas, `BASE_URL`) | Media | Medio | Probar la instalación en el servidor final antes de entregar |

---

## 9. Condiciones comerciales

- **Modelo:** precio fijo con alcance definido (el detalle de funcionalidades es el del punto 2).
- **Forma de pago propuesta:** 40 % al aprobar, 30 % en la entrega de prueba, 30 % en la entrega final.
- **Validez del presupuesto:** 30 días, dado el contexto de inflación.
- **No incluye:** IVA, hosting, dominio, mantenimiento posterior, capacitación ni pasarela de pago real (el pago es simulado).
- **Garantía:** corrección de errores del alcance acordado durante 30 días posteriores a la entrega.

---

## 10. Lista de verificación (cátedra)

- [x] Requerimientos definidos (puntos 2.2 a 2.6)
- [x] Estimación documentada
- [ ] Estimaciones revisadas por todo el equipo
- [x] Riesgos identificados
- [x] Costos indirectos incluidos
- [x] Contingencias calculadas
- [x] Términos comerciales definidos
- [ ] **Sueldos actualizados con CESSI** (pendiente)
- [ ] **Confirmar cantidad real de semanas y horas** (pendiente)
