# Personal prompts and favorites conventions

Phase 7 activates the shared prompt entity's `personal` source. Personal prompts are always private drafts whose owner is derived from the authenticated user. Forms cannot submit ownership, source, visibility, status or publication data. Policies require exact ownership for view, update, duplicate, copy and delete; administrator prompt permissions provide no private-workspace bypass.

Personal prompt mutations use dedicated Actions while reusing the Phase 6 validation, tag normalization and category rules. A personal edit increments its revision. Duplication creates another private personal draft for the same authenticated owner and copies tags. Deletion remains soft deletion.

`prompt_favorites` stores one fact per user and library prompt through a composite primary key. Adding a favorite requires the Phase 6 current visibility scope; favorites pages intersect stored facts with that scope again so later audience, publication or category changes remove access immediately. Removing a favorite deletes only the authenticated user's relationship.

Recently used prompts derive from `prompt_uses` rather than mutable counters. Results include the user's own current personal prompts and library prompts still visible through `PromptAccess`. Search and category/tag filtering are applied after ownership or audience scoping.
