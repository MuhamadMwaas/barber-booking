# Provider Resource - Complete Documentation

## 📋 Overview

This document provides a complete overview of the **ProviderResource** that has been created for managing service providers (employees) in the Barber Booking system.

## 🗂️ Files Created

### 1. Main Resource
- **File**: `app/Filament/Resources/Providers/ProviderResource.php`
- **Purpose**: Main resource configuration for providers
- **Features**:
  - Filters query to show only users with 'provider' role
  - Includes TimeOffs and ScheduledWorks relation managers
  - Custom navigation icon (scissors)

### 2. Schemas

#### Form Schema
- **File**: `app/Filament/Resources/Providers/Schemas/ProviderForm.php`
- **Sections**:
  - Personal Information (profile image, first name, last name)
  - Contact Information (email, phone, address, city)
  - Account Settings (branch, language, status, password)
  - Additional Information (notes)

#### Infolist Schema
- **File**: `app/Filament/Resources/Providers/Schemas/ProviderInfolist.php`
- **Displays**:
  - Provider profile with image
  - Contact and account information
  - Statistics (services, bookings, earnings, ratings)
  - Earnings breakdown (current month, total)

#### Table Schema
- **File**: `app/Filament/Resources/Providers/Tables/ProvidersTable.php`
- **Columns**:
  - Profile image, full name, phone
  - Branch, services count, appointments count
  - Upcoming leaves count, status, language
- **Actions**:
  - View, Edit, Add Leave (row action)
- **Filters**:
  - Active/Inactive status
  - Branch
  - Language
  - Has upcoming leaves

### 3. Pages

#### List Page
- **File**: `app/Filament/Resources/Providers/Pages/ListProviders.php`
- **Features**: Standard list page with create action

#### Create Page
- **File**: `app/Filament/Resources/Providers/Pages/CreateProvider.php`
- **Features**:
  - Auto-assigns 'provider' role
  - Handles profile image upload
  - Redirects to view page after creation

#### Edit Page
- **File**: `app/Filament/Resources/Providers/Pages/EditProvider.php`
- **Features**:
  - Handles profile image upload
  - Redirects to view page after update

#### View Page
- **File**: `app/Filament/Resources/Providers/Pages/ViewProvider.php`
- **Features**:
  - **Header Actions**:
    - Edit action
    - Add Hourly Leave (modal with form)
    - Add Daily Leave (modal with form)
  - **Widgets**: ProviderLeaveStatsWidget
  - **Tabs**: Info, Time Offs, Scheduled Works

### 4. Relation Managers

#### TimeOffsRelationManager
- **File**: `app/Filament/Resources/Providers/RelationManagers/TimeOffsRelationManager.php`
- **Features**:
  - Display leave type, dates, duration, reason, status
  - Create/Edit/Delete leaves via modals
  - Filters: type, reason, upcoming, past, active
  - Auto-calculates duration for hourly and daily leaves
  - Status badges (upcoming, active, past)

#### ScheduledWorksRelationManager
- **File**: `app/Filament/Resources/Providers/RelationManagers/ScheduledWorksRelationManager.php`
- **Features**:
  - Display work schedule by day
  - Shows start/end times, break minutes, working hours
  - Create/Edit/Delete schedule entries
  - Filters: day of week, work day/day off

### 5. Widgets

#### ProviderLeaveStatsWidget
- **File**: `app/Filament/Resources/Providers/Widgets/ProviderLeaveStatsWidget.php`
- **Statistics**:
  - Total leaves this year
  - Total days used (full-day leaves)
  - Total hours used (hourly leaves)
  - Upcoming leaves count
  - Current month leaves
  - Active leaves (currently on leave)
- **Features**: Charts for each stat

### 6. Custom Pages

#### ManageProviderLeaves
- **File**: `app/Filament/Pages/ManageProviderLeaves.php`
- **View**: `resources/views/filament/pages/manage-provider-leaves.blade.php`
- **Features**:
  - Overview of ALL provider leaves across the system
  - Statistics cards (total, upcoming, active, this month)
  - Comprehensive table with filters:
    - Provider name
    - Leave type
    - Reason
    - Status filters (upcoming, past, active, this month, this year)
  - Displays provider, type, dates, duration, reason, branch, status

## 🌐 Translations

### Arabic Translations
- **File**: `lang/ar/resources.php`
- **Key**: `provider_resource`
- **Includes**: All labels, messages, and UI text in Arabic

### English Translations
- **File**: `lang/en/resources.php`
- **Key**: `provider_resource`
- **Includes**: All labels, messages, and UI text in English

## 🎯 Features Summary

### ✅ Leave Management
- ✅ Add hourly leaves (specific hours in a day)
- ✅ Add daily leaves (full days)
- ✅ View leave history
- ✅ Filter leaves by status, type, reason
- ✅ Leave statistics and widgets
- ✅ Centralized page for all provider leaves

### ✅ Schedule Management
- ✅ View provider work schedule
- ✅ Manage weekly work hours
- ✅ Break time configuration
- ✅ Day off management

### ✅ Provider Information
- ✅ Complete profile view
- ✅ Statistics (services, earnings, ratings)
- ✅ Contact information
- ✅ Account settings

### ✅ Table Features
- ✅ Advanced filters
- ✅ Search functionality
- ✅ Quick actions (view, edit, add leave)
- ✅ Badge indicators for status

## 🔧 Usage

### Accessing Provider Resource
1. Navigate to **Providers** in the admin panel
2. Click on a provider to view details
3. Use tabs to switch between Info, Time Offs, and Scheduled Works

### Adding Leaves
**Method 1: From Provider View Page**
- Click "Add Hourly Leave" or "Add Daily Leave" buttons
- Fill in the form
- Submit

**Method 2: From Time Offs Tab**
- Go to Time Offs relation manager
- Click "Create"
- Select leave type and fill in details
- Submit

### Viewing All Leaves
- Navigate to **All Provider Leaves** page
- Use filters to find specific leaves
- View statistics at the top

## 📝 Notes

- The resource key is `provider_resource` to avoid conflicts with existing `provider` translation keys
- All providers are automatically assigned the 'provider' role upon creation
- Leave duration is automatically calculated based on dates/times
- Widget charts show trends over time
- The system validates leave overlaps (can be extended with custom validation)

## 🚀 Future Enhancements (Optional)

- Calendar view for leaves
- Email notifications for leave requests
- Leave approval workflow
- Export leave reports
- Conflict detection with existing appointments
- Annual leave balance tracking

---

**Created**: December 2025
**Version**: 1.0
**Framework**: FilamentPHP v4 + Laravel
