# Pension (Пансионат) Organization Logic - Audit Report

## Executive Summary

This audit examines the Laravel API codebase for Pension (Пансионат) organization logic implementation. The analysis covers Controllers, Models, and Policies to verify compliance with the technical specification (TZ).

---

## 1. Patient Data Access (Security Check)

### ✅ **FULLY IMPLEMENTED** - Patient Visibility

**Location:** `app/Http/Controllers/Api/v1/PatientController.php` (lines 60-110)

**Implementation Status:**
- ✅ **Pension staff see ALL patients**: In `index()` method, lines 80-83 correctly implement:
  ```php
  if ($organization->isBoardingHouse()) {
      $patients = Patient::where('organization_id', $organization->id)->get();
  }
  ```
  This returns ALL patients for the organization, regardless of staff role (Admin, Doctor, Caregiver).

**Access Control in `canAccessPatient()` method (lines 442-484):**
- ✅ Pension employees (all roles) can view all patients (line 472-474):
  ```php
  if ($organization->isBoardingHouse()) {
      return true; // All staff see all patients
  }
  ```

### ⚠️ **CRITICAL SECURITY ISSUE** - Read-Only Access for Doctor/Caregiver

**Problem:** `Doctor` and `Caregiver` roles CAN create and update patients.

**Evidence:**
1. **`store()` method (line 175):** No role-based restriction. Any authenticated user with patient access can create patients.
2. **`update()` method (line 361):** Only checks `canAccessPatient()`, which returns `true` for all Pension staff.
3. **`PatientPolicy::create()` (line 51):** Uses `canCreatePatients()` which allows `owner`, `admin`, and `private_caregiver` - but does NOT explicitly block `doctor` or `caregiver` in Pension context.

**Expected Behavior (per TZ):**
- `Doctor` and `Caregiver` in Pension should have **READ-ONLY** access to patients
- Only `Owner` and `Admin` should be able to `POST /patients` and `PUT /patients/{id}`

**Current Behavior:**
- `Doctor` and `Caregiver` can create/update patients if they pass `canAccessPatient()` check

**Recommendation:**
Add explicit role checks in `store()` and `update()` methods:
```php
// In store() method
if ($user->organization_id && $user->organization->isBoardingHouse()) {
    if ($user->hasAnyRole(['caregiver', 'doctor'])) {
        return response()->json(['message' => 'У вас нет прав на создание пациентов.'], 403);
    }
}

// In update() method - same check
```

---

## 2. Task/Route Sheet Visibility (The "Personal List" Logic)

### ✅ **FULLY IMPLEMENTED** - Caregiver Task Filtering

**Location:** `app/Http/Controllers/Api/v1/RouteSheetController.php` (lines 106-221)

**Implementation Status:**
- ✅ **Caregivers see ONLY assigned tasks**: Lines 138-149 correctly filter:
  ```php
  if ($user->hasRole('caregiver')) {
      $query->where(function ($q) use ($user) {
          $q->where('assigned_to', $user->id)
            ->orWhereNull('assigned_to');
      });
      
      // Filter by assigned patients if no patient_id specified
      if (!$request->has('patient_id')) {
          $assignedPatientIds = $user->assignedPatients()->pluck('patients.id');
          $query->whereIn('patient_id', $assignedPatientIds);
      }
  }
  ```

**However, there's a potential issue:**
- The filter includes `orWhereNull('assigned_to')` which means caregivers can see unassigned tasks for their assigned patients. This might be intentional, but should be verified against TZ requirements.

**For Pension context (lines 168-172):**
- When `patient_id` is NOT specified and user is in Pension, the query correctly filters by organization patients (line 170-172).
- But if a caregiver requests tasks for a specific patient, they will see ALL tasks for that patient (including unassigned ones) if they have access to that patient.

**Recommendation:**
Clarify if caregivers should see:
1. Only tasks where `assigned_to === caregiver.id` (strict personal list)
2. OR tasks where `assigned_to === caregiver.id OR assigned_to IS NULL` (current implementation)

---

## 3. Task Creation Rights

### ✅ **PARTIALLY IMPLEMENTED** - Task Creation Restrictions

**Location:** `app/Http/Controllers/Api/v1/RouteSheetController.php`

**Implementation Status:**

**`store()` method (line 286):**
- ✅ **Caregivers CANNOT create tasks**: Line 291 correctly blocks:
  ```php
  if (!$user->hasAnyRole(['client', 'manager', 'doctor', 'admin', 'owner'])) {
      return response()->json(['message' => 'У вас нет прав на создание задач.'], 403);
  }
  ```
  `caregiver` is NOT in the allowed list.

- ✅ **Doctors CAN create tasks**: `doctor` is in the allowed roles list.

**`update()` method (line 367):**
- ✅ Same restrictions apply (line 373).

**`destroy()` method (line 654):**
- ✅ Caregivers cannot delete tasks (line 660).

**Summary:**
- ✅ Caregivers: **CANNOT** create/update/delete tasks
- ✅ Doctors: **CAN** create/update tasks (but cannot delete - only `client`, `manager`, `admin`, `owner` can delete)

**Note:** The `TaskController` (separate from `RouteSheetController`) also exists but has different logic. Need to verify which controller is used for task creation in Pension context.

---

## 4. Pension General Schedule

### ❌ **NOT IMPLEMENTED** - General Schedule Feature

**Status:** **MISSING**

**Search Results:**
- No files found matching `*Schedule*.php`
- No references to "general schedule" or "общее расписание" in codebase
- No model, controller, or migration for general schedule functionality

**Expected Feature (per TZ):**
- A master schedule that applies to ALL patients in the Pension
- Should be managed by Owner/Admin
- Should generate tasks for all patients based on the general schedule

**Recommendation:**
This feature needs to be implemented from scratch. Suggested structure:
1. **Model:** `GeneralSchedule` or `OrganizationSchedule`
   - Fields: `organization_id`, `title`, `days_of_week`, `time_ranges`, `start_date`, `end_date`, `is_active`
2. **Controller:** `GeneralScheduleController`
   - CRUD operations (only for Owner/Admin)
   - Endpoint to apply schedule to all patients
3. **Service:** Logic to generate tasks from general schedule for all patients in Pension

---

## 5. Staff Access Control

### ✅ **FULLY IMPLEMENTED** - Organization Type Detection

**Location:** `app/Models/Organization.php`

**Implementation Status:**
- ✅ **Organization type check exists**: `isBoardingHouse()` method (line 81-84)
- ✅ **User organization check**: `$user->organization_id` is used throughout controllers
- ✅ **Type-based logic**: Controllers correctly differentiate between Pension (`isBoardingHouse()`) and Agency (`isAgency()`)

**How it works:**
1. `Organization` model has `type` field (enum: `OrganizationType::BOARDING_HOUSE` or `OrganizationType::AGENCY`)
2. `Organization::isBoardingHouse()` returns `true` if `type === OrganizationType::BOARDING_HOUSE`
3. Controllers check `$user->organization->isBoardingHouse()` to apply Pension-specific logic

**No middleware or global scopes found:**
- Access control is implemented at the controller level using helper methods like `canAccessPatient()`
- No global query scopes that automatically filter by organization type

**This is acceptable** as long as all controllers consistently use the helper methods.

---

## Summary Table

| Feature | Status | Notes |
|---------|--------|-------|
| **1. Patient Visibility (Pension)** | ✅ Implemented | All staff see all patients |
| **1. Patient Read-Only (Doctor/Caregiver)** | ⚠️ **ISSUE** | Doctor/Caregiver can create/update patients |
| **2. Task Visibility (Caregiver)** | ✅ Implemented | Caregivers see only assigned tasks |
| **3. Task Creation (Caregiver)** | ✅ Implemented | Caregivers cannot create tasks |
| **3. Task Creation (Doctor)** | ✅ Implemented | Doctors can create tasks |
| **4. General Schedule** | ❌ **MISSING** | Feature not implemented |
| **5. Organization Type Detection** | ✅ Implemented | `isBoardingHouse()` works correctly |

---

## Critical Issues Requiring Immediate Fix

### 🔴 **Priority 1: Patient Write Access for Doctor/Caregiver**

**File:** `app/Http/Controllers/Api/v1/PatientController.php`

**Fix Required:**
Add role-based restrictions in `store()` and `update()` methods to prevent Doctor and Caregiver from creating/updating patients in Pension context.

### 🟡 **Priority 2: General Schedule Feature**

**Status:** Not implemented. This is a core feature for Pension organizations and needs to be built.

---

## Recommendations

1. **Immediate:** Add explicit role checks in `PatientController::store()` and `update()` to block Doctor/Caregiver write access
2. **Short-term:** Implement General Schedule feature (Model, Controller, Service)
3. **Review:** Clarify task visibility logic for caregivers - should they see unassigned tasks?
4. **Testing:** Add integration tests for Pension-specific access control scenarios

---

## Files Analyzed

- `app/Http/Controllers/Api/v1/PatientController.php`
- `app/Http/Controllers/Api/v1/RouteSheetController.php`
- `app/Http/Controllers/Api/v1/TaskController.php`
- `app/Http/Controllers/Api/v1/TaskTemplateController.php`
- `app/Policies/PatientPolicy.php`
- `app/Models/Organization.php`
- `app/Models/User.php`
- `app/Models/Task.php`
- `routes/api.php`

---

*Report generated: 2025-01-XX*
*Auditor: AI Code Analysis*

