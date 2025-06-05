# Garante API

Garante is an integrity and accountability enforcement platform for Nigerian business people and professionals. This API provides the backend functionality for managing user accounts, businesses, guarantees, disputes, and trust scores.

## Features

- User Authentication with Laravel Sanctum
- Profile Verification (NIN and BVN)
- Business Registration and Verification
- Business Member Management
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

### Profile Verification Endpoints

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

### Business Endpoints

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
```

#### Add Business Member
```
POST /api/businesses/{business}/members
Authorization: Bearer {token}
Content-Type: application/json

{
    "user_id": "integer",
    "role": "manager|staff"
}
```

#### Remove Business Member
```
DELETE /api/businesses/{business}/members
Authorization: Bearer {token}
Content-Type: application/json

{
    "profile_id": "integer"
}
```

#### Leave Business
```
POST /api/businesses/{business}/leave
Authorization: Bearer {token}
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

## Testing

Run the test suite:
```bash
php artisan test
```

## License

The Garante API is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
