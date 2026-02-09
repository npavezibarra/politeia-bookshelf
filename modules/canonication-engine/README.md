# Canonication Engine (NG)
https://chatgpt.com/g/g-p-689f61d3cfbc8191845062baadc05805-politeia-book-session/c/6983cc3e-0108-8325-b422-f9a960e4f6d0

## 1. Purpose of the Module
Canonicalization is needed to prevent duplicate representations of the same intellectual work while keeping the user experience flexible and forgiving. The module separates the idea of a Canonical Book (the abstract work) from a User Book (the specific edition or entry a user owns or creates). The goal is to avoid duplicate canonical works for clean aggregation and analytics, while preserving the freedom for users to enter books quickly and in their own terms.

## 2. Core Concepts
A Canonical Book represents the abstract intellectual work, independent of specific editions or ownership. A User Book represents the concrete edition or instance a user adds to their library. Both are required: Canonical Books provide a stable identity for aggregation and discovery, while User Books preserve real-world editions and personal ownership. Canonical uniqueness matters for analytics, social features, and cross-user aggregation; without it, data fragments and duplicates undermine insights and recommendations.

## 3. Design Constraints
User input must remain fast and permissive. Dirty data is accepted at ingestion time. Cleanup is deferred and progressive. There must be no blocking UX during book creation.

## 4. Canonication Strategy
Duplicates are detected asynchronously and handled by proposing merges rather than enforcing them. The system uses confidence levels to decide whether a merge is safe, suggested, or requires confirmation. Relinking is preferred over deletion, and user data is always preserved.

## 5. Subsystems Overview
- Detection Engine
  Runs periodically (cron), finds potential canonical duplicates, and produces merge candidates only.
- Resolution Engine
  Applies rules and/or AI assistance to decide whether a merge is safe, suggested, or requires confirmation.
- Relinking Engine
  Safely reassigns User Books, migrates dependent data, and removes redundant canonical records.
- User Confirmation Layer
  Provides minimal frontend UX to confirm ambiguous corrections and never blocks core workflows.

## 6. Multilanguage Canonical Model
Canonical identity is language-agnostic while titles are localized. Storage and display language are separated to avoid fragmenting canonical identity across languages. This reduces duplication and prevents multilingual systems from splitting the same work into multiple canonical records.

## 7. Explicit Non-Goals (Important)
This module does NOT clean data at ingestion time. This module does NOT block book creation. This module does NOT enforce canonical purity synchronously.
