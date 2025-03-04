# Architecture

This document explains the internal architecture of Secure Custom Fields.

## Core Components

### 1. Field Management System

- Field type registration and validation
- Field rendering and display
- Data storage and retrieval
- Value sanitization and escaping

### 2. Post Type Management

- Custom post type registration
- Advanced configuration options
- WordPress core integration
- Rewrite rules and permalinks

### 3. Security Layer

- Input validation and sanitization
- Context-aware output escaping
- Permission and capability management
- Nonce verification

## Plugin Structure

The plugin is organized into several key directories:

### Core Directories

- **includes/** - Core functionality
  - **fields/** - Field type definitions
  - **admin/** - Admin interface
  - **api/** - Public API
- **assets/** - JS, CSS, and images

### Directory Responsibilities

1. **includes/**
   - Core plugin functionality
   - Field type handling
   - Post type management
   - API endpoints

2. **fields/**
   - Individual field type classes
   - Field validation logic
   - Field rendering code
   - Field storage handling

3. **admin/**
   - Admin interface components
   - Settings pages
   - Field group management
   - Post type configuration

4. **api/**
   - Public API endpoints
   - Integration points
   - External access methods

## Loading Process

### 1. Plugin Initialization

- Load dependencies
- Set up autoloader
- Initialize core classes

### 2. WordPress Integration

- Register post types
- Add hooks and filters
- Set up admin menus

### 3. Feature Registration

- Register field types
- Set up API endpoints
- Initialize components

### 4. Admin Interface Setup

- Load admin scripts
- Set up field management
- Configure settings pages

## Data Flow

1. **Input Processing**
   - Form submission
   - API requests
   - AJAX calls

2. **Data Validation**
   - Type checking
   - Format validation
   - Security verification

3. **Storage**
   - WordPress post meta
   - Custom tables
   - Caching

4. **Output**
   - Template functions
   - REST API responses
   - Admin interface
