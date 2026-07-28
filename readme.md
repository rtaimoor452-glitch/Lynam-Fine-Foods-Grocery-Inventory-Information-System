# Lynam Fine Foods Grocery Inventory Information System

## Assignment Cover Sheet

| Field | Detail |
|-------|--------|
| **Student Name and Number** | *(Fill in your name and student number)* |
| **Programme** | 2526_TMD1 |
| **Lecturer Name** | Paul Laird |
| **Module/Subject Title** | Programming for Information Systems (B9IS123) |
| **Assignment Title** | Lynam Fine Foods Grocery Inventory Information System |

By submitting this assignment, I confirm that:

- This assignment is all my own work.
- Any sources used have been referenced.
- I have followed the Generative AI instructions and scale set out in the Assignment Brief.
- I have read the College rules regarding academic integrity in the QAH Part B Section 3, and the Generative AI Guidelines, and understand that penalties will be applied accordingly if work is found not to be my own.
- I understand that all work submitted may be code-matched to show any similarities with other work.

---

## Project Overview

This is a proof-of-concept **Grocery Inventory Information System** developed for the module **Programming for Information Systems (B9IS123)** at Dublin Business School.

The system is designed for **Lynam Fine Foods**, an independent grocery, bakery and deli shop located at 5 Farmhill Road, Goatstown, Dublin 14, Ireland. This is an academic project and is not an official system developed for the organisation.

## Business Problem

Lynam Fine Foods is a small independent shop that may rely on manual methods such as paper lists or spreadsheets to track inventory. Manual stock management can lead to:

- No centralised record of products and stock levels
- Expired products going unnoticed
- Low-stock items not being flagged for reordering
- Difficulty searching for specific product information
- Human error in recording stock data

This system provides a simple web-based solution where a shop administrator can manage products through a single interface with full CRUD functionality, search, filtering, sorting and stock alerts.

## Technologies

| Layer | Technology |
|-------|------------|
| Frontend | HTML5, CSS, Vanilla JavaScript |
| API Communication | JavaScript Fetch API |
| Backend API | PHP with PDO |
| Database | SQLite |
| Version Control | Git and GitHub |

## Architecture

```
HTML5 / CSS Frontend
        │
        ▼
JavaScript Fetch API
        │
        ▼
  PHP JSON API
        │
        ▼
 SQLite Database
```

All CRUD operations use asynchronous JavaScript Fetch API calls to a PHP backend API. The API returns JSON responses with appropriate HTTP status codes. No full-page refreshes or traditional form submissions are used.

## Features

### CRUD Operations

- **Create** a new product
- **Read** all products or a single product by ID
- **Update** an existing product
- **Delete** a product with confirmation

### Additional Features

- Search products by name
- Filter products by category
- Sort by name, price or quantity
- Low-stock warning when quantity is below 5
- Summary statistics: total products, total stock quantity, low-stock count
- Success and error messages
- Edit and Delete buttons on each product row
- Cancel Edit button

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `api/products.php` | Get all products |
| GET | `api/products.php?id=1` | Get one product by ID |
| POST | `api/products.php` | Create a new product |
| PUT | `api/products.php?id=1` | Update a product |
| DELETE | `api/products.php?id=1` | Delete a product |

## Database Schema

```sql
CREATE TABLE IF NOT EXISTS products (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    product_name TEXT NOT NULL,
    category TEXT NOT NULL,
    price REAL NOT NULL CHECK(price >= 0),
    quantity INTEGER NOT NULL CHECK(quantity >= 0),
    supplier TEXT,
    expiry_date TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);
```

## Folder Structure

```
├── api/
│   └── products.php
├── config/
│   └── database.php
├── css/
│   └── style.css
├── db/
│   └── products.db
├── js/
│   └── app.js
├── tests/
│   ├── test_validation.php
│   └── test_integration.php
├── .gitignore
├── Index.html
└── README.md
```

## Validation

Input is validated on both the frontend (JavaScript) and backend (PHP):

- Product name is required
- Category is required
- Price must be numeric and not negative
- Quantity must be a whole number and not negative
- Expiry date must be a valid date format when provided
- Unnecessary whitespace is trimmed
- Backend validation is enforced regardless of frontend checks

## How to Run

1. Ensure PHP is installed on your system.
2. Clone this repository.
3. Navigate to the project directory.
4. Start a local PHP development server:
   ```bash
   php -S localhost:8000
   ```
5. Open `http://localhost:8000/Index.html` in your browser.

## Documentation

Full project documentation is available in the linked Google Docs document:

*(Google Docs link will be added here)*

## Attribution

Code suggestions were obtained with assistance from ChatGPT. The student reviewed, adapted, tested and documented the material used.

A full attribution summary is included in the project documentation.

## Licence

This project is developed for academic purposes as part of a Dublin Business School assignment.
