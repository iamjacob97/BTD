# 🚔 Police Incident Management System (BTD)

A full-featured, Dockerized web-based system for police officers to log, search, and manage incident reports, people, vehicles, fines, and audit trails. This project streamlines daily tasks and maintains a clean, auditable record of operations for public safety enforcement.

---

## 🧱 Features

- 🔐 Secure login with role-based access control (Officer/Admin)
- 📊 Officer dashboard with recent incidents and quick stats
- 👤 People and Vehicle search & management
- 📄 Incident reporting and editing
- 🚗 Ownership mapping between people and vehicles
- ⚖️ Fine management with offence constraints
- 📚 Audit trail with detailed tracking of all database changes
- 🛠️ Admin tools for managing users, offences, and fines
- 📦 Fully containerized using Docker

---

## 📦 Dockerized Deployment

1. Install Docker
2. Clone this repository
3. In your terminal, navigate to the project root
4. Run:

```bash
docker compose up
```

Access the system at `http://localhost:8080`.

---

## 🗂️ Project Structure

```
project-root/
├── html/
│   └── cw2/         # PHP, JS, CSS files
├── mariadb/         # DB schema and init scripts
├── mariadb-data/    # Persistent DB volume
├── php-apache/      # Apache-PHP Dockerfile
├── docker-compose.yml
```

---

## 🗄️ Database Design

- 9 relational tables with crow’s foot notation
- Key relationships: User ↔ Officer ↔ Incident ↔ Fine, Vehicle, Person, Ownership
- Enforced through foreign key constraints

---

## 🧭 Functional Overview

- 📌 Dashboard: Overview of activity and stats
- 🔍 Search: People, vehicles, incidents with autofill and AJAX
- ➕ CRUD: Add/edit/delete operations for all core entities
- 🔐 Admin Tools: User/officer creation, offences, fines, audit filtering

---

## 👨‍💻 Developer Highlights

- 🐳 Docker-based deployment
- 🧠 Entity relationship modeling
- ⚙️ Modular PHP with AJAX validation
- 📲 Mobile-friendly responsive CSS
- 🧾 Comprehensive session handling and audit logging

---

## 👩‍💻 Contributors

- **Jacob Abraham** — System Design, Full-Stack Dev

---

## 📚 Documentation

- `TechnicalDoc.pdf` – Architecture & ERD explanation
- `UserManual.pdf` – Officer-facing guide
