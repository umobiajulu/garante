# Garante API

Garante is an integrity and accountability enforcement platform for Nigerian business people and professionals. This API provides the backend functionality for managing user accounts, businesses, guarantees, disputes, and trust scores.

## Features

- User Authentication with Laravel Sanctum
- Profile Verification (NIN and BVN)
- Business Registration and Verification
- Business Member Management through Invitation System
  - Secure invitation-based member addition
  - 7-day invitation expiry
  - Role-based access control (owner, manager, staff)
  - Maximum 10 members per business
  - Members can leave business at any time (except owners)
  - Automatic member removal on profile deletion
  - One business per verified profile (owner or member)
  - Cannot create or join another business while in a business
- Business Bank Account Management
  - Multiple accounts per business
  - External API integration for account verification
  - Secure account information storage
  - Role-based access control for viewing and managing accounts
- Subscription Management
  - Fixed rate of ₦5,000 per month
  - Automatic subscription date calculation
  - Admin-only subscription management
  - Subscription account tracking
  - Subscription history for businesses
  - Subscription status checking for users
- Profile Management
  - Soft deletion with reason tracking
  - Prevention of fraudulent recreations
  - Deletion history maintenance
- Guarantee Creation and Management
- Dispute Resolution System
- Professional Trust Score System

## Requirements

- PHP 8.2+
- Laravel 10.x
- SQLite (for testing) or MySQL 8.0+
- Composer

## Installation

1. Clone the repository:
```bash
git clone <repository-url>
cd garante
```

2. Install dependencies:
```bash
composer install
```

3. Copy the environment file:
```bash
cp .env.example .env
```

4. Configure your database in the .env file:
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=garante
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

5. Generate application key:
```bash
php artisan key:generate
```

6. Run migrations:
```bash
php artisan migrate
```

## API Documentation

### Authentication Endpoints

#### Register User
```
POST /api/register
Content-Type: application/json

{
    "name": "string",
    "email": "string",
    "password": "string",
    "password_confirmation": "string",
    "phone_number": "string"
}
```

#### Login
```
POST /api/login
Content-Type: application/json

{
    "email": "string",
    "password": "string"
}
```

#### Logout
```
POST /api/logout
Authorization: Bearer {token}
```

#### Get Current User
```
GET /api/me
Authorization: Bearer {token}
```

### Profile Management Endpoints

#### Submit Profile for Verification
```
POST /api/profiles
Authorization: Bearer {token}
Content-Type: multipart/form-data

{
    "nin": "string",
    "bvn": "string",
    "address": "string",
    "state": "string",
    "city": "string",
    "profession": "string",
    "id_document": "file",
    "address_document": "file"
}
```

#### Delete Profile
```
DELETE /api/profiles/{profile}
Authorization: Bearer {token}
Content-Type: application/json

{
    "deletion_reason": "string (10-1000 characters)"
}
```

### Profile Verification Endpoints

#### Verify NIN
```
POST /api/verify-nin
Authorization: Bearer {token}
Content-Type: application/json

{
    "nin": "string",
    "nin_phone": "string",
    "nin_dob": "date"
}
```

#### Verify BVN
```
POST /api/verify-bvn
Authorization: Bearer {token}
Content-Type: application/json

{
    "bvn": "string",
    "bvn_phone": "string",
    "bvn_dob": "date"
}
```

#### Get Verification Status
```
GET /api/verification-status
Authorization: Bearer {token}
```

### Business Management Endpoints

#### Create Business
```
POST /api/businesses
Authorization: Bearer {token}
Content-Type: multipart/form-data

{
    "name": "string",
    "registration_number": "string",
    "business_type": "sole_proprietorship|partnership|limited_company",
    "address": "string",
    "state": "string",
    "city": "string",
    "registration_document": "file"
}

Note: A verified profile can only create a business if they are not already a member or owner of another business.
```

#### Get Business Details
```
GET /api/businesses/{business}
Authorization: Bearer {token}
```

#### Update Business
```
PUT /api/businesses/{business}
Authorization: Bearer {token}
Content-Type: multipart/form-data

{
    "name": "string",
    "address": "string",
    "state": "string",
    "city": "string",
    "registration_document": "file"
}
```

#### Verify Business (Arbitrator Only)
```
POST /api/businesses/{business}/verify
Authorization: Bearer {token}
Content-Type: application/json

{
    "status": "verified|rejected"
}
```

#### List Business Members
```
GET /api/businesses/{business}/members
Authorization: Bearer {token}

Response:
{
    "message": "Members retrieved successfully",
    "members": [
        {
            "id": "integer",
            "name": "string",
            "email": "string",
            "role": "owner|manager|staff"
        }
    ]
}
```

#### Leave Business
```
POST /api/businesses/{business}/leave
Authorization: Bearer {token}

Response:
{
    "message": "Successfully left the business"
}

Possible Errors:
- 422: "Business owner cannot leave the business"
- 422: "Cannot leave business with unresolved disputes"
- 404: "Not a member of this business"
```

#### List Business Accounts
```
GET /api/businesses/{business}/accounts
Authorization: Bearer {token}

Response:
{
    "message": "Accounts retrieved successfully",
    "accounts": [
        {
            "id": "integer",
            "business_id": "integer",
            "bank_name": "string",
            "account_name": "string",
            "account_number": "string",
            "status": "string",
            "created_at": "timestamp",
            "updated_at": "timestamp"
        }
    ]
}
```

#### Add Business Account
```
POST /api/businesses/{business}/accounts
Authorization: Bearer {token}
Content-Type: application/json

{
    "bank_name": "string",
    "account_name": "string",
    "account_number": "string (numeric)"
}

Response:
{
    "message": "Account added successfully",
    "account": {
        "id": "integer",
        "business_id": "integer",
        "bank_name": "string",
        "account_name": "string",
        "account_number": "string",
        "status": "string",
        "created_at": "timestamp",
        "updated_at": "timestamp"
    }
}
```

#### Get Business Account Details
```
GET /api/businesses/{business}/accounts/{account}
Authorization: Bearer {token}

Response:
{
    "message": "Account retrieved successfully",
    "account": {
        "id": "integer",
        "business_id": "integer",
        "bank_name": "string",
        "account_name": "string",
        "account_number": "string",
        "status": "string",
        "created_at": "timestamp",
        "updated_at": "timestamp"
    }
}
```

#### Remove Business Account
```
DELETE /api/businesses/{business}/accounts/{account}
Authorization: Bearer {token}

Response:
{
    "message": "Account removed successfully"
}
```

### Business Invitation System

#### Send Business Invitation
```
POST /api/businesses/{business}/invitations
Authorization: Bearer {token}
Content-Type: application/json

{
    "user_id": "integer",
    "role": "manager|staff"
}
```

#### List Pending Invitations
```
GET /api/invitations
Authorization: Bearer {token}
```

#### Accept Invitation
```
POST /api/invitations/{invitation}/accept
Authorization: Bearer {token}
```

#### Reject Invitation
```
POST /api/invitations/{invitation}/reject
Authorization: Bearer {token}
```

### Subscription Management Endpoints

#### List Business Subscriptions
```
GET /api/businesses/{business}/subscriptions
Authorization: Bearer {token}
```

#### Create Subscription (Admin Only)
```
POST /api/businesses/{business}/subscriptions
Authorization: Bearer {token}
Content-Type: application/json

{
    "duration_months": "integer (1-60)",
    "notes": "string (optional)"
}
```

#### Delete Subscription (Admin Only)
```
DELETE /api/businesses/{business}/subscriptions/{subscription}
Authorization: Bearer {token}
```

#### Check Subscription Status
```
GET /api/subscription-status
Authorization: Bearer {token}

Response:
{
    "message": "string",
    "has_active_subscription": "boolean",
    "details": {
        "business_name": "string",
        "business_id": "integer",
        "subscription_id": "integer",
        "start_date": "datetime",
        "end_date": "datetime",
        "duration_months": "integer",
        "amount": "decimal"
    }
}
```

### Guarantee Endpoints

#### Create Guarantee
```
POST /api/guarantees
Authorization: Bearer {token}
Content-Type: application/json

{
    "business_id": "integer",
    "buyer_id": "integer",
    "service_description": "string",
    "price": "decimal",
    "terms": {
        "delivery_date": "date",
        "payment_terms": "string",
        "deliverables": ["string"]
    },
    "expires_at": "date|null"
}
```

#### List Guarantees
```
GET /api/guarantees
Authorization: Bearer {token}
Query Parameters:
- status: string
- type: "active|expired"
```

#### Get Guarantee Details
```
GET /api/guarantees/{guarantee}
Authorization: Bearer {token}
```

#### Update Guarantee Status
```
PUT /api/guarantees/{guarantee}/status
Authorization: Bearer {token}
Content-Type: application/json

{
    "status": "completed|cancelled|disputed"
}
```

#### Provide Consent
```
POST /api/guarantees/{guarantee}/consent
Authorization: Bearer {token}
```

#### Accept Guarantee
```
POST /api/guarantees/{guarantee}/accept
Authorization: Bearer {token}
```

### Dispute Endpoints

#### Create Dispute
```
POST /api/disputes
Authorization: Bearer {token}
Content-Type: application/json

{
    "guarantee_id": "integer",
    "reason": "string",
    "description": "string",
    "evidence": {
        // Array of evidence files/links
    }
}
```

#### Submit Defense
```
POST /api/disputes/{dispute}/defense
Authorization: Bearer {token}
Content-Type: application/json

{
    "defense_description": "string",
    "defense": {
        // Array of defense files/links
    }
}

Notes:
- When defense is submitted, dispute status automatically changes to 'in_review'
- Once in 'in_review' status, arbitrator can resolve the dispute immediately
- If no defense is submitted within 3 working days, arbitrator can resolve the dispute
```

#### List Disputes
```
GET /api/disputes
Authorization: Bearer {token}
```

#### Get Dispute Details
```
GET /api/disputes/{dispute}
Authorization: Bearer {token}
Response includes:
{
    "dispute": {
        "id": "integer",
        "guarantee_id": "integer",
        "initiated_by": "integer",
        "reason": "string",
        "description": "string",
        "evidence": {
            // Array of evidence files/links
        },
        "defense": {
            // Array of defense files/links (if submitted)
        },
        "defense_description": "string|null",
        "status": "pending|in_review|resolved",
        "resolution_notes": "string|null",
        "resolved_by": "integer|null",
        "resolved_at": "datetime|null"
    }
}
```

#### Resolve Dispute (Arbitrator Only)
```
POST /api/disputes/{dispute}/resolve
Authorization: Bearer {token}
Content-Type: application/json

{
    "decision": "refund|partial_refund|no_refund",
    "refund_amount": "decimal|null", // Required if decision is partial_refund
    "notes": "string"
}

Response includes:
{
    "message": "string",
    "verdict": {
        "id": "integer",
        "dispute_id": "integer",
        "guarantee_id": "integer",
        "arbitrator_id": "integer",
        "decision": "string",
        "refund_amount": "decimal|null",
        "notes": "string",
        "evidence_reviewed": {
            "evidence": {},
            "defense": {}
        },
        "decided_at": "datetime"
    },
    "restitution": {  // Only included if refund or partial_refund
        "id": "integer",
        "verdict_id": "integer",
        "amount": "decimal",
        "status": "pending"
    }
}
```

### Restitution Endpoints

#### Process Restitution (Seller Only)
```
POST /api/restitutions/{restitution}/process
Authorization: Bearer {token}
Content-Type: application/json

{
    "proof_of_payment": "string"
}

Response includes:
{
    "message": "string",
    "restitution": {
        "id": "integer",
        "verdict_id": "integer",
        "amount": "decimal",
        "status": "processed",
        "proof_of_payment": "string",
        "processed_at": "datetime"
    }
}
```

#### Complete Restitution (Buyer or Arbitrator Only)
```
POST /api/restitutions/{restitution}/complete
Authorization: Bearer {token}

Response includes:
{
    "message": "string",
    "restitution": {
        "id": "integer",
        "verdict_id": "integer",
        "amount": "decimal",
        "status": "completed",
        "proof_of_payment": "string",
        "processed_at": "datetime",
        "completed_by": "integer",
        "completed_at": "datetime"
    }
}
```

### Trust Score System

The platform implements a trust score system that affects sellers based on dispute resolutions and restitution compliance:

#### Trust Score Deductions
- Full refund verdict: -50 points
- Partial refund verdict: -20 points
- No refund verdict: No change

#### Trust Score Restoration
- Completed full refund restitution: +50 points
- Completed partial refund restitution: +20 points

Notes:
- Trust scores are capped at 100 points
- Trust scores cannot go below 0 points
- Trust score restoration only occurs after successful completion of restitution

## Business Rules

1. Profile Restrictions:
   - A user can only have one verified profile
   - A profile must be verified to create or join a business
   - A verified profile can only be associated with one business at a time (as owner or member)

2. Business Membership:
   - Maximum 10 members per business
   - Members can have roles: owner, manager, or staff
   - Business owner cannot leave the business
   - Members can leave unless there are unresolved disputes
   - Cannot join or create another business while being a member or owner
   - Must leave current business before joining or creating another one

3. Subscription Management:
   - Fixed rate of ₦5,000 per month
   - Only admins (@garante.admin domain) can manage subscriptions
   - All business members can view subscription status
   - Subscription dates are automatically calculated
   - Hard deletes for all subscription-related operations

4. Dispute Restrictions:
   - Business owners cannot update business details while there are unresolved disputes
   - Business owners cannot update their profile details while there are unresolved disputes
   - Business members cannot update their profile details while there are unresolved disputes
   - Updates are allowed once all disputes are resolved
   - These restrictions help maintain data integrity during dispute resolution

## Authorization

The API uses a role-based access control system:

- Admin users (identified by @garante.admin email domain)
  - Full access to all features
  - Exclusive access to subscription management
  - Can verify businesses and profiles
- Business Owners
  - Can manage their business details
  - Can manage business members
  - Can manage business bank accounts
  - Can view subscriptions and subscription accounts
- Business Members
  - Can view business details
  - Can view business bank accounts
  - Can view subscriptions and subscription accounts
  - Limited access based on role (manager/staff)

## Testing

Run the test suite:
```bash
php artisan test
```

## License

The Garante API is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
