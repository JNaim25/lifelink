# LifeLink: Intelligent Blood Inventory & Traceability System
### Course: Database Management Systems Laboratory (CSE 3522)
### Department of Computer Science & Engineering

---

## 1. Project Overview
**LifeLink** is an enterprise-grade, database-driven blood bank management web application developed for the **CSE 3522 Database Management Systems Laboratory**. The system manages voluntary blood donors, cold-chain refrigerated inventory bags, emergency hospital blood requisitions, and administrative issuance workflows with referential integrity, real-time SQL aggregation, cold-chain shelf-life tracking, and ACID-compliant transaction processing using **MySQL (via XAMPP)**, **PHP 8.2 (PDO)**, and **HTML5/Bootstrap 5.3**.

The codebase is organized into a clean **3-tier modular architecture** across dedicated folders:
* **`db/`**: Complete database tier containing SQL DDL/DML, views, composite indexes, sample seed data, and a one-command database reset utility.
* **`backend/`** (alias: **`back/`**): Pure PHP 8.2 backend tier providing PDO database connectivity with port 3307/3306 auto-failover, business logic services, session authentication, and JSON REST API endpoints.
* **`frontend/`** (alias: **`front/`**): Pure client-side presentation tier using standalone HTML (`.html`) files, responsive CSS, Bootstrap 5.3, Chart.js analytics, and asynchronous JavaScript (`fetch()` API) client modules.

Unlike basic CRUD blood bank projects that only store an aggregate count of blood units, LifeLink treats blood as a **perishable, serialized biological asset** (35-day shelf-life). Every collected blood bag has a unique serial barcode, collection date, dynamic cold-chain shelf-life countdown, testing state, and an end-to-end chain-of-custody.

---

## 2. Directory Structure

```
lifelink/
├── db/                       # Database Tier
│   ├── schema.sql            # 7-table 3NF relational schema DDL
│   ├── views.sql             # MySQL Views (view_blood_availability, view_emergency_queue)
│   ├── indexes.sql           # Composite B-tree indexes for query optimization
│   ├── seed.sql              # Realistic clinical sample data & bcrypt credentials
│   ├── queries_demo.sql      # Syllabus demonstration queries for viva inspection
│   └── reset_db.php          # One-command database restoration script
│
├── backend/                  # Backend Tier (PHP 8.2 PDO MySQL)
│   ├── config/
│   │   └── database.php      # PDO connection manager with port 3307/3306 failover
│   ├── includes/
│   │   ├── auth.php          # Session RBAC & authentication helpers
│   │   └── response.php      # Standardized JSON API response handlers
│   ├── services/
│   │   ├── blood_service.php # ABO/Rh compatibility & Smart Match algorithms
│   │   ├── inventory_service.php # Serialized unit shelf-life (35-day) & cold-chain
│   │   ├── request_service.php   # Requisition tickets & demand-vs-supply matrix
│   │   ├── issuance_service.php  # ACID transaction execution & The Blood Journey
│   │   └── donor_service.php     # Donor intake & 90-day cooldown calculation
│   └── api/
│       ├── auth.php          # User login, registration, session me, logout
│       ├── radar.php         # Real-time stock radar view endpoint
│       ├── search.php        # Smart Blood Match compatibility endpoint
│       ├── emergency.php     # Emergency requisition broadcast & triage queue
│       ├── journey.php       # 5-stage bio-traceability chain-of-custody endpoint
│       ├── requests.php      # Requisitions CRUD & ACID issuance transaction
│       ├── inventory.php     # Serialized bag registry, expiry alerts, discard
│       ├── donations.php     # Clinical intake sessions & inventory bag spawner
│       ├── donors.php        # Complete donor directory (SQL LEFT JOIN)
│       └── reports.php       # Live DBMS syllabus benchmark query runner
│
├── frontend/                 # Frontend Tier (Pure HTML, CSS, JS)
│   ├── css/
│   │   └── custom.css        # Clinical healthcare design & pulsing emergency badges
│   ├── js/
│   │   ├── api.js            # Asynchronous fetch client & toast notifications
│   │   ├── navbar.js         # Dynamic navigation header & emergency counter badge
│   │   ├── journey_modal.js  # Interactive 5-step stepper chain-of-custody modal
│   │   └── dashboard_charts.js # Chart.js radar & supply balance graphs
│   ├── index.html            # Landing page (Hero, Live Radar, Quick Tracer)
│   ├── search.html           # Feature 1: Smart Blood Match Search
│   ├── emergency.html        # Feature 2: Emergency Request & Triage Queue
│   ├── login.html            # Authentication portal with 1-click demo logins
│   ├── register.html         # User registration with medical profile toggle
│   ├── donor_dashboard.html  # Feature 5: Donor 90-Day Cooldown & History
│   ├── requester_dashboard.html # Hospital requisitions tracker & cancel ticket
│   ├── new_request.html      # Hospital requisition submission ticket form
│   ├── admin_dashboard.html  # Feature 8 & 9: KPIs, Triage, Cold-Chain Alerts
│   ├── admin_inventory.html  # Unit-level serialized inventory bag management
│   ├── admin_requests.html   # Requisition queue & Feature 7 ACID Issuance
│   ├── admin_donations.html  # Donor intake session logger
│   ├── admin_donors.html     # Feature 10: Complete donor directory (LEFT JOIN)
│   └── admin_reports.html    # Interactive DBMS syllabus demonstrator
│
├── back                      # NTFS Directory Junction pointing to backend/
├── front                     # NTFS Directory Junction pointing to frontend/
├── index.html                # Project root entry redirecting to frontend/index.html
├── index.php                 # Project root router redirecting to frontend/index.html
└── README.md                 # Project technical documentation & viva guide
```

---

## 3. Database Schema & Normalization (3NF)

The database models the transfusion network using **7 normalized entities in Third Normal Form (3NF)**:

```
+-----------------------------------------------------------------------------------+
|                                  DATABASE ENTITIES                                |
+-----------------------------------------------------------------------------------+
| 1. users            : Common credentials & identity for Admin, Donor, Requester   |
| 2. blood_groups     : Master catalog of the 8 ABO/Rh types & compatibility rules  |
| 3. donors           : Medical profile & 90-day cooldown interval tracker          |
| 4. donations        : Records of individual donation sessions (BP, Hemoglobin)    |
| 5. blood_inventory  : Individual serialized blood bags with dynamic shelf-life    |
| 6. blood_requests   : Requisition tickets categorized by clinical urgency         |
| 7. blood_issuances  : Audit trail linking fulfilled requests to physical bags     |
+-----------------------------------------------------------------------------------+
```

### Academic Normalization Proof:
* **First Normal Form (1NF)**:
  * Every attribute holds atomic (scalar) values.
  * No multi-valued attributes or repeating groups.
  * Every table has a distinct primary key (`user_id`, `blood_group_id`, `donor_id`, `donation_id`, `inventory_id`, `request_id`, `issuance_id`).
* **Second Normal Form (2NF)**:
  * The schema is in 1NF.
  * Contains **no partial functional dependencies**. Because all tables use single-column surrogate primary keys, a partial dependency on a composite key is impossible.
* **Third Normal Form (3NF)**:
  * The schema is in 2NF.
  * Contains **no transitive dependencies** ($X \rightarrow Y \rightarrow Z$).
    * Blood group definitions and compatibility rules are factored out into `blood_groups` instead of being redundantly stored inside `blood_inventory` or `donors`.
    * `blood_issuances` references only `request_id` and `inventory_id` rather than duplicating patient, hospital, or bag details.

---

## 4. SQL Requirements-to-Feature Mapping

| DBMS Requirement | Application Feature | Concrete SQL Query / Implementation |
| :--- | :--- | :--- |
| **SELECT** | Blood Search & Radar | `SELECT bg.group_name, COUNT(bi.inventory_id) FROM blood_groups bg ...` |
| **INSERT** | Bag Registration & Donation | `INSERT INTO blood_inventory (bag_code, blood_group_id, ...) VALUES (...)` |
| **UPDATE** | Request Approval / Cooldown | `UPDATE blood_requests SET status = 'APPROVED' WHERE request_id = ?` |
| **DELETE** | Pending Request Cancel | `DELETE FROM blood_requests WHERE request_id = ? AND status = 'PENDING'` |
| **COUNT()** | Dashboard Counters | `SELECT COUNT(*) FROM blood_inventory WHERE status = 'AVAILABLE'` |
| **SUM()** | Inventory Viability | `SUM(CASE WHEN status = 'AVAILABLE' AND expiry_date >= CURDATE() THEN 1 ELSE 0 END)` |
| **MIN() / MAX()** | Expiry & Cooldown Dates | `SELECT MIN(expiry_date) FROM blood_inventory WHERE status = 'AVAILABLE'` |
| **GROUP BY** | Blood Availability Radar | `SELECT bg.group_name, COUNT(bi.inventory_id) FROM ... GROUP BY bg.blood_group_id, bg.group_name` |
| **HAVING** | Critical Stock Warning | `SELECT bg.group_name, COUNT(...) AS stock FROM ... GROUP BY bg.group_name HAVING stock < 3` |
| **INNER JOIN** | Request Traceability | `SELECT br.patient_name, bg.group_name FROM blood_issuances bi INNER JOIN blood_requests br ON ...` |
| **LEFT JOIN** | Complete Donor Directory | `SELECT u.full_name, COUNT(don.donation_id) FROM donors d LEFT JOIN donations don ON ...` *(Shows donors with 0 donations)* |
| **Subquery** | Demand vs. Supply Matrix | Correlated subqueries in `SELECT` computing available stock and pending requests per blood group. |
| **VIEW** | Reusable Stock Radar | `CREATE VIEW view_blood_availability AS ...` (Queried live by Homepage & Dashboard) |
| **TRANSACTION** | Atomic Blood Issuance | `$pdo->beginTransaction();` $\rightarrow$ `SELECT ... FOR UPDATE;` $\rightarrow$ `INSERT issuances;` $\rightarrow$ `UPDATE inventory;` $\rightarrow$ `$pdo->commit();` |
| **INDEX** | Fast Search Optimization | `CREATE INDEX idx_inventory_status_expiry ON blood_inventory (blood_group_id, status, expiry_date);` |

---

## 5. System Features
1. **Smart Blood Match**: Rule-based biological compatibility matching (evaluates universal donor O- and universal recipient AB+ substitutions).
2. **Emergency Blood Request Triage**: High-priority requisition queue with pulsing visual alerts for trauma cases.
3. **Blood Availability Radar**: Real-time 8-card inventory grid powered by MySQL View `view_blood_availability`.
4. **Cold-Chain Expiry Warning**: Automated detection of blood units expiring within $\le 7$ days to prevent clinical wastage.
5. **Donor 90-Day Cooldown Tracker**: Progress bar computing days remaining before the next medically safe donation.
6. **The Signature "Blood Journey"**: Interactive 5-stage visual stepper tracking any bag:
   $$\text{Donor Intake} \longrightarrow \text{Collection} \longrightarrow \text{Cold Storage} \longrightarrow \text{Hospital Requisition} \longrightarrow \text{Transfusion Issuance}$$
7. **Transaction-Based Blood Issuance**: Multi-step ACID execution with automatic rollback on stock deficit.
8. **Demand vs. Supply Balance Matrix**: Real-time analytical matrix comparing hospital requisition demand against cold storage supply.
9. **Admin Analytics Dashboard**: Real-time KPI cards and Chart.js inventory visualizers.
10. **Database-Powered Multi-Parameter Search**: Search by blood group, barcode serial, and storage location.

---

## 6. Installation & Execution Guide

### Prerequisites
* Windows 10 or 11
* **XAMPP** (with Apache and MySQL)
* **PHP 8.2** (included with standard XAMPP in `C:\xampp\php\php.exe`)

### Step-by-Step Setup:
1. **Start MySQL in XAMPP**:
   * Open the **XAMPP Control Panel**.
   * Click **Start** next to MySQL. *(LifeLink automatically detects whether your MySQL runs on port 3307 or 3306!)*

2. **Initialize Database & Seed Data (One-Command Reset)**:
   Open Command Prompt or PowerShell in the project directory and run:
   ```cmd
   C:\xampp\php\php.exe db/reset_db.php
   ```
   *This creates the database `blood_bank_db` and loads `db/schema.sql`, `db/views.sql`, `db/indexes.sql`, and `db/seed.sql` into MySQL.*

3. **Start the Web Application**:
   You can run the project in either of two ways:

   * **Option A (Built-in PHP Server - Recommended)**:
     ```cmd
     C:\xampp\php\php.exe -S 127.0.0.1:5000
     ```
     Then open your browser at: **`http://127.0.0.1:5000`** (or `http://127.0.0.1:5000/frontend/index.html`)

   * **Option B (XAMPP Apache htdocs)**:
     Copy or symlink the `lifelink` directory into `C:\xampp\htdocs\lifelink`.
     Start Apache in XAMPP Control Panel and open: **`http://localhost/lifelink/frontend/index.html`**

---

## 7. Default Demonstration Credentials

| Role | Email Address | Password | Purpose in Demo |
| :--- | :--- | :--- | :--- |
| **System Admin** | `admin@lifelink.org` | `Admin@123` | Control center, emergency triage, and ACID issuance transaction |
| **Volunteer Donor** | `donor1@gmail.com` | `Password@123` | Donor profile, 90-day cooldown interval bar, and past donation history |
| **Hospital Requester** | `requester1@hospital.org` | `Password@123` | Hospital blood requisition, ticket tracking, cancellation |

*(Tip: On the `frontend/login.html` page, click any of the **One-Click Quick-Fill** buttons to populate credentials instantly without typing).*

---

## 8. 10-Step Viva & Inspection Presentation Script

1. **Step 1 (Homepage Showcase)**: Open `http://127.0.0.1:5000/frontend/index.html`. Point out the healthcare UI, system metrics, and the Live Blood Availability Radar powered by MySQL View `view_blood_availability`.
2. **Step 2 (Smart Blood Match)**: Click **Smart Blood Match** (`frontend/search.html`), select B+, enter 2 units. Show exact match units and compatible substitute units with live shelf-life countdowns.
3. **Step 3 (Signature Blood Journey)**: Click any bag barcode (e.g. `BAG-2026-A199`) to show the 5-stage chain-of-custody modal (Donor $\rightarrow$ Collection $\rightarrow$ Storage $\rightarrow$ Request $\rightarrow$ Issuance).
4. **Step 4 (Submit Emergency Request)**: Click **Emergency Request** (`frontend/emergency.html`) and submit an emergency request for 2 units of O- for "Dhaka Medical Trauma Unit".
5. **Step 5 (Admin Instant Alert)**: Sign in as Admin (`admin@lifelink.org`). The emergency ticket immediately appears with a pulsing red badge in the **Emergency Triage Queue** on `frontend/admin_dashboard.html`.
6. **Step 6 (Cold-Chain Expiry Feed)**: On the Admin Dashboard, show units expiring within 7 days and expired quarantine units.
7. **Step 7 (Demand vs. Supply Balance)**: Scroll down to Feature #9 and explain how SQL subqueries compare live demand against stock.
8. **Step 8 (Execute ACID Issuance Transaction)**: In **Blood Requests** (`frontend/admin_requests.html`), click **[Issue]** on the emergency ticket. Explain how PHP starts a PDO transaction (`$pdo->beginTransaction()`), locks viable bags with `FOR UPDATE`, logs issuances, updates bag status to `ISSUED`, marks the request `FULFILLED`, and commits.
9. **Step 9 (Live Stock Recalculation)**: Return to the Radar on the homepage. Point out that available O- units have automatically decremented in MySQL.
10. **Step 10 (DBMS Showcase Page)**: Click **DBMS Queries Demo** (`frontend/admin_reports.html`) to show your examiner all mandatory syllabus queries running live on MySQL (`COUNT`, `SUM`, `AVG`, `MIN`, `MAX`, `GROUP BY` + `HAVING`, Correlated Subqueries, Views, and Multi-Table `INNER JOIN`).

---

## 9. Group Member Work Distribution Matrix (4–5 Students)

| Member | Module Responsibility | Tables Handled | DBMS / SQL Operations Owned |
| :--- | :--- | :--- | :--- |
| **Member 1** | Database Architecture & Auth | `users`, `blood_groups` | DDL schema creation, PK/FK constraints, bcrypt password hashing, session RBAC. |
| **Member 2** | Donor Portal & 90-Day Cooldown | `donors`, `donations` | SQL `LEFT JOIN`, date interval arithmetic (cooldown calculation), donor profile. |
| **Member 3** | Inventory & Expiry Radar | `blood_inventory`, `blood_groups` | `CREATE VIEW view_blood_availability`, `CREATE INDEX`, shelf-life alerts, `GROUP BY`. |
| **Member 4** | Requests & Emergency Triage | `blood_requests`, `blood_groups` | Emergency triage queue, request status workflow, DML `DELETE` cancellation. |
| **Member 5** | Issuance Engine & Analytics | `blood_issuances`, all tables | `$pdo->beginTransaction()`, `$pdo->commit()`, `$pdo->rollBack()`, `SELECT ... FOR UPDATE`, and Chart.js analytics. |

---

## 10. Viva Preparation: Questions & Answers

### Q1: Why is your database in 3NF?
> **Answer**: 
> 1. It is in **1NF** because all column values are atomic and every table has a defined primary key.
> 2. It is in **2NF** because all tables use single-column surrogate primary keys, so partial functional dependencies cannot exist.
> 3. It is in **3NF** because there are no transitive dependencies ($X \rightarrow Y \rightarrow Z$). Non-key attributes depend only on the primary key. For example, blood group compatibility rules are in `blood_groups`, and issuance records reference only foreign keys rather than storing redundant patient or bag information.

### Q2: Why is a database transaction required for blood issuance?
> **Answer**: 
> Blood issuance consists of multiple write operations: verifying stock, inserting audit records into `blood_issuances`, updating `blood_inventory.status = 'ISSUED'`, and updating `blood_requests.status = 'FULFILLED'`. If an error or system crash occurs mid-process, the database would become inconsistent (e.g. inventory decremented without an issuance record, or request fulfilled without allocated units). A database transaction guarantees **Atomicity**—either all operations commit together (`$pdo->commit()`), or all modifications are rolled back (`$pdo->rollBack()`).

### Q3: Why did you create an index on `blood_inventory(blood_group_id, status, expiry_date)`?
> **Answer**: 
> Blood search, Smart Blood Match, and issuance transactions frequently filter by `blood_group_id = ? AND status = 'AVAILABLE' AND expiry_date >= CURDATE() ORDER BY expiry_date ASC`. A composite B-Tree index satisfies this query via an index range scan in $O(\log N)$ time, eliminating a full table scan and avoiding an expensive filesort.

### Q4: What is the purpose of your MySQL View?
> **Answer**: 
> `view_blood_availability` pre-compiles real-time aggregation queries combining `COUNT()`, `SUM()`, `MIN(expiry_date)`, and `CASE` statements. It acts as an abstraction layer so that both our public homepage and admin dashboard query a clean, single relational view instead of repeating complex SQL aggregation logic across multiple scripts.
