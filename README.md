# Préstamo de Equipos ECCI — Frontend

[![Rama principal](https://img.shields.io/badge/branch-main-blue)](https://github.com/danielcleves/prestamos-equipos-ecci-frontend/tree/main)
[![Laravel](https://img.shields.io/badge/Laravel-13-red)](https://laravel.com)
[![Licencia](https://img.shields.io/badge/license-MIT-green)](LICENSE)

Frontend del sistema **Préstamo de Equipos** de la Universidad ECCI (Semestre 8 · Gestión de Software). Es el **repositorio de frontend**, que implementa la **interfaz de usuario** con Laravel + Blade.

> ⚙️ El backend (API y datos) vive en su propio repositorio:
> [**prestamos-equipos-ecci-backend**](https://github.com/danielcleves/prestamos-equipos-ecci-backend)

## 📋 Descripción del proyecto

El sistema gestiona el **préstamo de equipos** de la universidad. Sus funcionalidades principales son:

- **Solicitar** un equipo.
- **Entregar** un equipo al solicitante.
- **Devolver** un equipo.
- **Registrar retrasos** en las devoluciones.

Este repositorio es la **interfaz de usuario**. Se comunica con el **backend (API REST)** para obtener y enviar datos; **no accede directamente a la base de datos** (el frontend va → API → backend → BD).

## 🧰 Stack tecnológico

- **Laravel 13** (framework PHP).
- **PHP 8.3+**.
- **Blade** (motor de plantillas para las vistas).
- Consume la API REST del backend.

## ✅ Requisitos previos

- **PHP** 8.3 o superior.
- **Composer** 2.x.

Verifica que estén instalados:

```sh
php -v
composer -V
```

## 🚀 Instalación y puesta en marcha

```sh
# 1. Clonar el repositorio
git clone git@github.com:danielcleves/prestamos-equipos-ecci-frontend.git
cd prestamos-equipos-ecci-frontend

# 2. Instalar dependencias
composer install

# 3. Configurar variables de entorno
cp .env.example .env

# 4. Generar la clave de la aplicación
php artisan key:generate

# 5. Levantar el servidor de desarrollo
php artisan serve
```

El frontend quedará disponible en `http://127.0.0.1:8000` (o el puerto que definas). Asegúrate de configurar la URL del **backend** en el `.env` para que el frontend se conecte a la API correcta.

## 🌿 Estrategia de ramas

Flujo de trabajo del repositorio: `main` (producción) → `develop` (integración + QA) → `feature/*` (desarrollo de cada tarea).

```
main ───────► producción (solo DevOps)
   ▲
   │ merge cuando QA aprueba develop
develop ────► integración + QA principal
   ▲
   │ PR desde feature
feature/* ──► trabajo del desarrollador
```

### Ciclo de trabajo

1. El desarrollador crea `feature/*` desde `develop`, desarrolla su tarea y abre un **Pull Request hacia `develop`**.
2. **QA aprueba/rechaza el PR.** El dev solo lo solicita; es QA quien decide si el código entra a `develop`.
3. El PR aprobado se fusiona a `develop`. Es ahí donde **QA lo prueba de verdad**, con el código integrado junto a las demás features.
4. Cuando QA valida todo en `develop`, se hace el merge de `develop` → `main`.
5. **DevOps** despliega la aplicación desde `main`.

### Las dos validaciones de QA

QA interviene en dos momentos distintos dentro del flujo:

| Momento | Dónde | Qué hace QA |
|---|---|---|
| Al PR | revisa `feature/*` antes de entrar a `develop` | Aprobación de código (conflictos, buenas prácticas, tests) |
| Tras el merge | ya en `develop` con todo integrado | Prueba funcional de la aplicación y OK para pasar a `main` |

### Reglas de aprobación por nivel

Cada salto de nivel requiere la aprobación del rol correspondiente:

- `feature/* → develop`: aprueba **QA** (el PR del desarrollador).
- `develop → main`: aprueba **QA** (validación funcional) y lo ejecuta **DevOps**.

## 📁 Estructura del proyecto

```
app/           Lógica de la aplicación (controllers, models)
resources/views/   Vistas Blade (interfaz de usuario)
routes/        Definición de rutas
public/        Punto de entrada pública y assets (CSS/JS)
tests/         Pruebas automáticas
```

## 👥 Participantes

| Nombre | Rol | Perfil de GitHub |
|---|---|---|
| Emmanuel Valencia | Product Owner / Analista de Negocio / Gestor del Proyecto / Delivery Manager | [Emm2704](https://github.com/Emm2704) |
| Daniel Cleves | Líder Técnico | [danielcleves](https://github.com/danielcleves) |
| Alejandro Molina | UX/UI Designer | [AlejoMolina09](https://github.com/AlejoMolina09) |
| Jose López (Kota) | Desarrollo Frontend | [kotaErn650](https://github.com/kotaErn650) |
| José David | Desarrollo Backend | [JoseMedina-prog](https://github.com/JoseMedina-prog) |
| Sebastián Rodríguez | QA y Automatización de Pruebas | [SebasRCam](https://github.com/SebasRCam) |
| Victor Marín | DevOps, Despliegue y Observabilidad | [V53M](https://github.com/V53M) |

## 📄 Licencia

Proyecto académico basado en [Laravel](https://laravel.com), distribuido bajo la [licencia MIT](https://opensource.org/licenses/MIT).
