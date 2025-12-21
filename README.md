# 📚 Magento 2 Learning Journey

> Complete guide to Magento 2 module development - From Zero to Hero in 30 Days

[![Magento 2](https://img.shields.io/badge/Magento-2.4.x-orange?logo=magento)](https://magento.com/)
[![License](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](CONTRIBUTING.md)

---

## 📋 Table of Contents

- [About](#-about)
- [Quick Start](#-quick-start)
- [30-Day Study Plan](#-30-day-study-plan)
- [Documentation](#-documentation)
- [Module Structure](#-module-structure)
- [Commands Reference](#-commands-reference)
- [Resources](#-resources)

---

## 📖 About

This repository is your comprehensive guide to mastering Magento 2 module development. Whether you're a beginner or looking to advance your skills, this structured 30-day plan will take you from the basics to advanced topics.

### What You'll Learn

- ✅ Module structure and registration
- ✅ Controllers, Models, and Views
- ✅ Dependency Injection
- ✅ Plugins and Observers
- ✅ REST & GraphQL APIs
- ✅ Admin panel development
- ✅ Testing and best practices

---

## 🚀 Quick Start

### Minimum Module Structure

```
app/code/Vendor/ModuleName/
├── registration.php    # Required ❗
└── etc/
    └── module.xml      # Required ❗
```

### `registration.php`
```php
<?php
use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'Vendor_ModuleName',
    __DIR__
);
```

### `etc/module.xml`
```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Module/etc/module.xsd">
    <module name="Vendor_ModuleName"/>
</config>
```

---

## 📅 30-Day Study Plan

> [**📘 View Full Study Plan →**](./STUDY_PLAN_30_DAYS.md)

| Week | Focus | Topics |
|------|-------|--------|
| **Week 1** | Foundation | Registration, Module XML, Routes, Controllers, Models |
| **Week 2** | Data Layer | Blocks, Views, DI, Observers, Plugins |
| **Week 3** | API & Setup | WebAPI, Patches, CLI, Cron, EAV |
| **Week 4** | Admin | Configuration, UI Components, Indexers, Cache, ACL |
| **Week 5** | Advanced | Testing, GraphQL, Message Queues, Payment, Checkout |
| **Week 6** | Enterprise | Themes, Admin Grids/Forms, JavaScript, Final Project |

---

## 📖 Documentation

### Core Topics (Days 1-14)

| Day | Topic | Link |
|-----|-------|------|
| 01 | Registration | [📂 Go →](./docs/Day-01-Registration/) |
| 02 | Module XML | [📂 Go →](./docs/Day-02-Module-XML/) |
| 03 | Routes | [📂 Go →](./docs/Day-03-Routes/) |
| 04 | Controllers | [📂 Go →](./docs/Day-04-Controllers/) |
| 05 | Models | [📂 Go →](./docs/Day-05-Models/) |
| 06 | Blocks | [📂 Go →](./docs/Day-06-Blocks/) |
| 07 | Views & Layouts | [📂 Go →](./docs/Day-07-Views-Layouts/) |
| 08 | Dependency Injection | [📂 Go →](./docs/Day-08-Dependency-Injection/) |
| 09 | Observers | [📂 Go →](./docs/Day-09-Observers/) |
| 10 | Plugins | [📂 Go →](./docs/Day-10-Plugins/) |
| 11 | API & WebAPI | [📂 Go →](./docs/Day-11-API-WebAPI/) |
| 12 | Setup & Patches | [📂 Go →](./docs/Day-12-Setup-Patches/) |
| 13 | CLI Commands | [📂 Go →](./docs/Day-13-CLI-Commands/) |
| 14 | Cron Jobs | [📂 Go →](./docs/Day-14-Cron-Jobs/) |

### Advanced Topics (Days 15-26)

| Day | Topic | Link |
|-----|-------|------|
| 15 | EAV System | [📂 Go →](./docs/Day-15-EAV-System/) |
| 16 | XML Configuration | [📂 Go →](./docs/Day-16-XML-Configuration/) |
| 17 | UI Components | [📂 Go →](./docs/Day-17-UI-Components/) |
| 18 | Indexers | [📂 Go →](./docs/Day-18-Indexers/) |
| 19 | Caching | [📂 Go →](./docs/Day-19-Caching/) |
| 20 | ACL & Security | [📂 Go →](./docs/Day-20-ACL-Security/) |
| 21 | Testing | [📂 Go →](./docs/Day-21-Testing/) |
| 22 | GraphQL | [📂 Go →](./docs/Day-22-GraphQL/) |
| 23 | Message Queues | [📂 Go →](./docs/Day-23-Message-Queues/) |
| 24 | Payment Methods | [📂 Go →](./docs/Day-24-Payment-Methods/) |
| 25 | Checkout | [📂 Go →](./docs/Day-25-Checkout/) |
| 26 | Themes | [📂 Go →](./docs/Day-26-Themes/) |

### Practical Topics (Days 27-30)

| Day | Topic | Link |
|-----|-------|------|
| 27 | Admin Grids | [📂 Go →](./docs/Day-27-Admin-Grids/) |
| 28 | Admin Forms | [📂 Go →](./docs/Day-28-Admin-Forms/) |
| 29 | JavaScript & RequireJS | [📂 Go →](./docs/Day-29-JavaScript-RequireJS/) |
| 30 | Final Project | [📂 Go →](./docs/Day-30-Final-Project/) |

---

## 🏗️ Module Structure

```
app/code/Vendor/ModuleName/
│
├── 📄 registration.php
├── 📂 etc/
│   ├── module.xml
│   ├── di.xml
│   ├── events.xml
│   └── 📂 frontend/ & adminhtml/
│
├── 📂 Api/                  # Service Contracts
├── 📂 Block/                # View Blocks
├── 📂 Controller/           # Controllers
├── 📂 Model/                # Models & ResourceModels
├── 📂 Observer/             # Event Observers
├── 📂 Plugin/               # Plugins
├── 📂 Setup/                # Data & Schema Patches
├── 📂 view/                 # Templates & Layouts
└── 📂 Test/                 # Unit & Integration Tests
```

> **📘 Detailed Structure:** [MODULE_STRUCTURE.md](./MODULE_STRUCTURE.md)

---

## 🚀 Commands Reference

```bash
# Enable module
bin/magento module:enable Vendor_ModuleName

# Run setup upgrade
bin/magento setup:upgrade

# Compile DI
bin/magento setup:di:compile

# Deploy static content
bin/magento setup:static-content:deploy -f

# Clear cache
bin/magento cache:flush

# Check module status
bin/magento module:status
```

---

## 🔗 Resources

### Official Documentation
- [Adobe Commerce DevDocs](https://developer.adobe.com/commerce/)
- [Magento Coding Standards](https://developer.adobe.com/commerce/php/coding-standards/)

### Certification
- [Adobe Certified Professional - Developer](https://business.adobe.com/products/magento/certification.html)
- Exam: AD0-E717 | 60 Questions | 90 Minutes | 68% Pass

---

## 📝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

---

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

> [!TIP]
> **Start with Day 1!** Follow the 30-day plan for the best learning experience.

---

<p align="center">Made with ❤️ for the Magento Community</p>
