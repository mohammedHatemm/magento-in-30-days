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
| 01 | Registration | [📂 Go →](./Day-01-Registration/README.md) |
| 02 | Module XML | [📂 Go →](./Day-02-Module-XML/README.md) |
| 03 | Routes | [📂 Go →](./Day-03-Routes/README.md) |
| 04 | Controllers | [📂 Go →](./Day-04-Controllers/README.md) |
| 05 | Models | [📂 Go →](./Day-05-Models/README.md) |
| 06 | Blocks | [📂 Go →](./Day-06-Blocks/README.md) |
| 07 | Views & Layouts | [📂 Go →](./Day-07-Views-Layouts/README.md) |
| 08 | Dependency Injection | [📂 Go →](./Day-08-Dependency-Injection/README.md) |
| 09 | Observers | [📂 Go →](./Day-09-Observers/README.md) |
| 10 | Plugins | [📂 Go →](./Day-10-Plugins/README.md) |
| 11 | API & WebAPI | [📂 Go →](./Day-11-API-WebAPI/README.md) |
| 12 | Setup & Patches | [📂 Go →](./Day-12-Setup-Patches/README.md) |
| 13 | CLI Commands | [📂 Go →](./Day-13-CLI-Commands/README.md) |
| 14 | Cron Jobs | [📂 Go →](./Day-14-Cron-Jobs/README.md) |

### Advanced Topics (Days 15-26)

| Day | Topic | Link |
|-----|-------|------|
| 15 | EAV System | [📂 Go →](./Day-15-EAV-System/README.md) |
| 16 | XML Configuration | [📂 Go →](./Day-16-XML-Configuration/README.md) |
| 17 | UI Components | [📂 Go →](./Day-17-UI-Components/README.md) |
| 18 | Indexers | [📂 Go →](./Day-18-Indexers/README.md) |
| 19 | Caching | [📂 Go →](./Day-19-Caching/README.md) |
| 20 | ACL & Security | [📂 Go →](./Day-20-ACL-Security/README.md) |
| 21 | Testing | [📂 Go →](./Day-21-Testing/README.md) |
| 22 | GraphQL | [📂 Go →](./Day-22-GraphQL/README.md) |
| 23 | Message Queues | [📂 Go →](./Day-23-Message-Queues/README.md) |
| 24 | Payment Methods | [📂 Go →](./Day-24-Payment-Methods/README.md) |
| 25 | Checkout | [📂 Go →](./Day-25-Checkout/README.md) |
| 26 | Themes | [📂 Go →](./Day-26-Themes/README.md) |

### Practical Topics (Days 27-30)

| Day | Topic | Link |
|-----|-------|------|
| 27 | Admin Grids | [📂 Go →](./Day-27-Admin-Grids/README.md) |
| 28 | Admin Forms | [📂 Go →](./Day-28-Admin-Forms/README.md) |
| 29 | JavaScript & RequireJS | [📂 Go →](./Day-29-JavaScript-RequireJS/README.md) |
| 30 | Final Project | [📂 Go →](./Day-30-Final-Project/README.md) |

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
