# Implementation Plan: Personal Notes Platform

## Overview

This implementation plan breaks down the Personal Notes Platform into discrete coding steps. The platform enables users to register, login, manage notes, search by keywords, and filter by tags. Tasks progress from project setup through API implementation, frontend components, and testing.

## Current Status (Updated: March 9, 2026)

✅ **Completed:**
- Database schema created (all 5 required tables)
- Database connection utility with connection pooling
- PHP environment configured with pdo_mysql extension
- MySQL connection working with password `Telza#45Max`

🔄 **In Progress:**
- User registration endpoint (`register.php`) - needs minor fixes

❌ **Not Started:**
- Authentication system (login, logout, sessions)
- Note management API
- Search and filter functionality
- Frontend interface

## Implementation Roadmap

### ✅ Phase 1: Core Authentication System (IN PROGRESS)
1. ✅ **Fix `register.php`** - Remove warning and ensure consistent response format
2. 🔄 **Create `login.php`** - User authentication with session token generation
3. ❌ **Create `logout.php`** - Session termination
4. ❌ **Enhance `Database.php`** - Add session management methods

### ❌ Phase 2: Note Management API
1. ❌ **Create `notes.php`** - CRUD operations for notes
2. ❌ **Implement ownership validation** - Users can only access their own notes
3. ❌ **Add tag association logic** - Handle note-tag relationships

### ❌ Phase 3: Search & Filter Functionality
1. ❌ **Create `search.php`** - Keyword search with FULLTEXT indexing
2. ❌ **Create `tags.php`** - Tag management and filtering
3. ❌ **Implement combined search/filter** - Support both criteria simultaneously

### ❌ Phase 4: Frontend Interface
1. ❌ **Create basic HTML structure** - Authentication and note management pages
2. ❌ **Implement JavaScript logic** - API integration and UI updates
3. ❌ **Add error handling and validation** - Client-side feedback

### ❌ Phase 5: Testing & Optimization
1. ❌ **Write unit tests** - Validate core functionality
2. ❌ **Write property-based tests** - Ensure correctness properties
3. ❌ **Performance optimization** - Meet response time requirements

## Immediate Next Steps

### 1. Create `login.php` endpoint
- Location: `api/auth/login.php`
- Functionality:
  - Accept email and password
  - Verify credentials with `Database::verifyPassword()`
  - Generate session token using `bin2hex(random_bytes(32))`
  - Store session in `sessions` table with 24-hour expiration
  - Return session token in response

### 2. Create `logout.php` endpoint  
- Location: `api/auth/logout.php`
- Functionality:
  - Accept session token from headers
  - Delete session from `sessions` table
  - Return success response

### 3. Enhance `Database.php` with session methods
- Add methods:
  - `createSession($userId, $token, $expiresAt)`
  - `validateSession($token)`
  - `deleteSession($token)`
  - `getUserIdFromToken($token)`

### 4. Create authentication middleware
- Create `api/auth/middleware.php` or helper functions
- Functionality:
  - Extract token from `Authorization` header
  - Validate token and expiration
  - Return user ID for authenticated requests
  - Return 401 for invalid/missing tokens

## Tasks

- [x] 1. Set up project structure and configuration
  - [x] Directory structure: `api/`, `api/auth/` created
  - [x] Database configuration in `api/Database.php`
  - [x] PHP environment configured with pdo_mysql extension
  - [x] MySQL connection tested and working
  - _Requirements: 16.1, 16.2, 16.3, 16.4_

- [x] 2. Create database schema and migrations
  - [x] 2.1 Create users table with email, password_hash, created_at fields
    - [x] Unique index on email field
    - _Requirements: 15.1_
  
  - [x] 2.2 Create notes table with user_id, title, content, timestamps
    - [x] Foreign key constraint to users table
    - _Note: FULLTEXT index to be added later for search optimization_
    - _Requirements: 15.2_
  
  - [x] 2.3 Create tags table with name field
    - [x] Unique index on name field
    - _Requirements: 15.3_
  
  - [x] 2.4 Create note_tags junction table
    - [x] Composite primary key (note_id, tag_id)
    - [x] Foreign key constraints
    - _Requirements: 15.4_
  
  - [x] 2.5 Create sessions table with token and expires_at
    - [x] Unique index on token field
    - [x] Foreign key constraint to users table
    - _Requirements: 15.5_

- [ ] 3. Implement authentication API
  - [x] 3.1 Create database connection utility with PDO
    - [x] Implement connection pooling pattern
    - [x] Add error handling for connection failures
    - [x] Password hashing with bcrypt (cost factor 12)
    - _Requirements: 11.5_
  
  - [x] 3.2 Implement user registration endpoint (`/api/auth/register`)
    - [x] Validate email format and password strength
    - [x] Hash password using bcrypt with cost factor 12
    - [x] Create user record and return success response
    - [x] Fix: Remove unnecessary `use PDO;` statement (line 77)
    - [x] Fix: Ensure consistent error/success response structure
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5_
  
  - [ ] 3.3 Implement user login endpoint (`/api/auth/login`)
    - [ ] Verify credentials using password_verify()
    - [ ] Generate session token using random_bytes(32)
    - [ ] Store session with 24-hour expiration
    - [ ] Return session token in response
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5_
  
  - [ ] 3.4 Implement user logout endpoint (`/api/auth/logout`)
    - [ ] Invalidate session by deleting from database
    - [ ] Clear session data from client
    - _Requirements: 3.1, 3.2, 3.3_
  
  - [ ] 3.5 Create authentication middleware
    - [ ] Validate session token from request headers
    - [ ] Check token expiration
    - [ ] Return 401 for invalid/missing tokens
    - _Requirements: 11.1, 11.2, 18.1, 18.2, 18.3_

- [ ] 4. Implement note CRUD API
  - [ ] 4.1 Create note service class with validation
    - [ ] Validate title length (max 200 chars)
    - [ ] Validate content length (max 10000 chars)
    - [ ] Validate required fields
    - _Requirements: 20.1, 20.2, 20.4, 20.5_
  
  - [ ] 4.2 Implement create note endpoint (`POST /api/notes`)
    - [ ] Validate input data
    - [ ] Create note with current timestamp
    - [ ] Handle tag associations
    - [ ] Return created note with ID
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5_
  
  - [ ] 4.3 Implement get all notes endpoint (`GET /api/notes`)
    - [ ] Retrieve notes for authenticated user only
    - [ ] Include tag associations
    - [ ] Return paginated results
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5_
  
  - [ ] 4.4 Implement get single note endpoint (`GET /api/notes/{id}`)
    - [ ] Verify note ownership
    - [ ] Return 404 for non-existent notes
    - [ ] Include tag associations
    - _Requirements: 5.2, 5.3, 5.4_
  
  - [ ] 4.5 Implement update note endpoint (`PUT /api/notes/{id}`)
    - [ ] Validate input data
    - [ ] Update note and timestamp
    - [ ] Handle tag updates
    - [ ] Verify ownership before update
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5_
  
  - [ ] 4.6 Implement delete note endpoint (`DELETE /api/notes/{id}`)
    - [ ] Verify ownership before deletion
    - [ ] Delete associated note_tags entries
    - [ ] Return success response
    - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5_

- [ ] 5. Implement search API
  - [ ] 5.1 Create search service with full-text query builder
    - Build MySQL FULLTEXT search query
    - Handle boolean mode search
    - Include relevance scoring
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5_
  
  - [ ] 5.2 Implement search endpoint (`GET /api/search?q={keywords}`)
    - Parse search keywords
    - Execute full-text search query
    - Return notes with relevance scores
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5_

- [ ] 6. Implement filter API
  - [ ] 6.1 Create filter service with tag join builder
    - Build SQL query with note_tags joins
    - Handle single and multi-tag filtering
    - Implement INTERSECT logic for multi-tag
    - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5_
  
  - [ ] 6.2 Implement tag filter endpoint (`GET /api/notes?tag={tag_name}`)
    - Parse tag parameter
    - Execute filtered query
    - Return matching notes
    - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5_

- [ ] 7. Implement combined search and filter API
  - [ ] 7.1 Create combined query builder
    - Merge search and filter logic
    - Handle optional search and filter parameters
    - Return notes matching both criteria
    - _Requirements: 17.1, 17.2, 17.3, 17.4, 17.5_
  
  - [ ] 7.2 Implement combined endpoint
    - Accept both q and tag parameters
    - Execute combined query
    - Return filtered search results
    - _Requirements: 17.1, 17.2, 17.3, 17.4, 17.5_

- [ ] 8. Set up frontend structure
  - [ ] 8.1 Create HTML structure for index page
    - Set up Bootstrap grid layout
    - Include navigation header
    - Add main content area
    - _Requirements: 14.1, 14.2, 14.3, 14.4, 14.5_
  
  - [ ] 8.2 Create authentication pages (login, register)
    - Design form layouts with Bootstrap
    - Add input validation
    - Include loading indicators
    - _Requirements: 1.4, 2.5, 3.3_
  
  - [ ] 8.3 Create notes list page
    - Design note card layout
    - Add search bar component
    - Add tag filter dropdown
    - _Requirements: 14.1, 14.2, 14.3, 14.4, 14.5_
  
  - [ ] 8.4 Create note editor modal
    - Title and content textareas
    - Tag input field
    - Save and cancel buttons
    - _Requirements: 14.1, 14.2, 14.3, 14.4, 14.5_

- [ ] 9. Implement frontend JavaScript logic
  - [ ] 9.1 Create API utility module
    - Implement fetch wrapper with error handling
    - Add request/response interceptors
    - Handle authentication headers
    - _Requirements: 13.1, 13.2, 13.3, 13.4, 13.5_
  
  - [ ] 9.2 Implement authentication logic
    - Handle registration form submission
    - Handle login form submission
    - Store session token in memory
    - Handle logout functionality
    - _Requirements: 1.1, 1.2, 1.3, 2.1, 2.2, 3.1, 3.2, 3.3_
  
  - [ ] 9.3 Implement notes list logic
    - Fetch and render notes on page load
    - Handle note selection
    - Implement delete confirmation
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 7.1, 7.2, 7.3, 7.4, 7.5_
  
  - [ ] 9.4 Implement note editor logic
    - Open modal for new note
    - Populate form for existing note
    - Handle save with validation
    - Handle delete confirmation
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 6.1, 6.2, 6.3, 6.4, 6.5, 7.1, 7.2, 7.3, 7.4, 7.5_
  
  - [ ] 9.5 Implement search functionality
    - Handle search input changes
    - Debounce search requests
    - Display search results
    - Clear search on empty input
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5_
  
  - [ ] 9.6 Implement tag filtering
    - Handle tag selection
    - Apply multi-tag filter
    - Display filter status
    - Clear filters
    - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5_

- [ ] 10. Implement error handling and validation
  - [ ] 10.1 Create global error handler
    - Catch and display API errors
    - Show user-friendly messages
    - Log errors for debugging
    - _Requirements: 13.1, 13.2, 13.3, 13.4, 13.5_
  
  - [ ] 10.2 Implement client-side validation
    - Email format validation
    - Password strength validation
    - Title and content length validation
    - Tag length validation
    - _Requirements: 20.1, 20.2, 20.3, 20.4, 20.5_
  
  - [ ] 10.3 Add loading states
    - Show loading indicator during API calls
    - Disable buttons during processing
    - Show error state on failure
    - _Requirements: 1.4, 2.5, 3.3, 4.5, 5.5, 6.5, 7.5, 8.5, 9.5_

- [ ] 11. Write unit tests
  - [ ] 11.1 Write unit tests for authentication service
    - Test successful registration
    - Test duplicate email handling
    - Test invalid email validation
    - Test successful login
    - Test invalid credentials
    - _Requirements: 1.1, 1.2, 1.3, 2.1, 2.2_
  
  - [ ] 11.2 Write unit tests for note service
    - Test note creation with valid data
    - Test note creation with missing fields
    - Test note creation with title too long
    - Test note creation with content too long
    - Test note update functionality
    - Test note deletion
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 6.1, 6.2, 6.3, 6.4, 6.5, 7.1, 7.2, 7.3, 7.4, 7.5, 20.1, 20.2, 20.4, 20.5_
  
  - [ ] 11.3 Write unit tests for search service
    - Test search with single keyword
    - Test search with multiple keywords
    - Test search with no results
    - Test search with special characters
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5_
  
  - [ ] 11.4 Write unit tests for filter service
    - Test single tag filter
    - Test multi-tag filter with intersection
    - Test filter with no results
    - Test case-insensitive tag matching
    - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5_
  
  - [ ] 11.5 Write unit tests for validation utilities
    - Test email format validation
    - Test title length validation
    - Test content length validation
    - Test tag length validation
    - _Requirements: 20.1, 20.2, 20.3, 20.4, 20.5_

- [ ] 12. Write property-based tests
  - [ ] 12.1 Write property test for Property 1: Note ownership isolation
    - **Property 1: Note ownership isolation**
    - **Validates: Requirements 11.3**
    - For any note in the database, when a user retrieves notes, only notes belonging to that specific user should be returned
    - Generate random users and notes, verify users can only access their own notes
  
  - [ ] 12.2 Write property test for Property 2: Session token uniqueness
    - **Property 2: Session token uniqueness**
    - **Validates: Requirements 18.1**
    - For any active session, the session token must be unique across all sessions
    - Generate multiple concurrent sessions, verify no token collisions
  
  - [ ] 12.3 Write property test for Property 3: Note deletion cascade
    - **Property 3: Note deletion cascade**
    - **Validates: Requirements 7.2**
    - For any note that is deleted, all associated note_tags entries should also be automatically deleted
    - Create note with tags, delete note, verify note_tags entries are removed
  
  - [ ] 12.4 Write property test for Property 4: Search keyword matching
    - **Property 4: Search keyword matching**
    - **Validates: Requirements 8.2**
    - For any search query, all returned notes should contain the search keywords in either the title or content field
    - Generate search queries and verify all results contain the keywords
  
  - [ ] 12.5 Write property test for Property 5: Multi-tag filtering intersection
    - **Property 5: Multi-tag filtering intersection**
    - **Validates: Requirements 9.2**
    - For any multi-tag filter request, the returned notes should match ALL specified tags
    - Generate notes with various tag combinations, verify intersection logic
  
  - [ ] 12.6 Write property test for Property 6: Data validation constraints
    - **Property 6: Data validation constraints**
    - **Validates: Requirements 20.1, 20.2**
    - For any note creation or update request, if the title exceeds 200 characters or content exceeds 10000 characters, the operation should be rejected
    - Generate titles and content of various lengths, verify validation rejects oversized inputs
  
  - [ ] 12.7 Write property test for Property 7: Timestamp consistency
    - **Property 7: Timestamp consistency**
    - **Validates: Requirements 4.2, 6.1**
    - For any note, the created_at timestamp should remain unchanged after creation, while the updated_at timestamp should automatically update whenever the note is modified
    - Create note, verify created_at; update note, verify updated_at changes
  
  - [ ] 12.8 Write property test for Property 8: Search performance guarantee
    - **Property 8: Search performance guarantee**
    - **Validates: Requirements 8.4**
    - For any search request with up to 1000 notes, the search operation should complete within 500ms
    - Generate 1000 notes, measure search response time
  
  - [ ] 12.9 Write property test for Property 9: Tag association integrity
    - **Property 9: Tag association integrity**
    - **Validates: Requirements 19.3**
    - For any note-tag association, if a tag is no longer associated with any notes, it should remain in the database but be marked for cleanup
    - Create note with tag, delete note, verify tag remains in database
  
  - [ ] 12.10 Write property test for Property 10: Password hashing consistency
    - **Property 10: Password hashing consistency**
    - **Validates: Requirements 11.5**
    - For any user password, the stored password_hash must be a bcrypt hash
    - Generate passwords, verify stored hash matches bcrypt format

- [ ] 13. Integration and wiring
  - [ ] 13.1 Wire authentication flow
    - Connect registration to user creation
    - Connect login to session creation
    - Connect logout to session invalidation
    - _Requirements: 1.1, 1.2, 1.3, 2.1, 2.2, 3.1, 3.2, 3.3_
  
  - [ ] 13.2 Wire note CRUD operations
    - Connect frontend forms to API endpoints
    - Implement optimistic UI updates
    - Handle errors gracefully
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 6.1, 6.2, 6.3, 6.4, 6.5, 7.1, 7.2, 7.3, 7.4, 7.5_
  
  - [ ] 13.3 Wire search and filter
    - Connect search bar to search API
    - Connect tag filter to filter API
    - Implement combined search and filter
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5, 9.1, 9.2, 9.3, 9.4, 9.5, 17.1, 17.2, 17.3, 17.4, 17.5_
  
  - [ ] 13.4 Implement session refresh mechanism
    - Check session validity on page load
    - Refresh expired sessions
    - Redirect to login on permanent expiration
    - _Requirements: 18.1, 18.2, 18.3, 18.4, 18.5_

- [ ] 14. Final checkpoint - Ensure all tests pass
  - Ensure all unit tests pass
  - Ensure all property-based tests pass
  - Verify search performance meets requirements
  - Verify security measures are in place
  - Ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties
- Unit tests validate specific examples and edge cases
- All implementation uses PHP for backend and JavaScript for frontend as specified in the design document
