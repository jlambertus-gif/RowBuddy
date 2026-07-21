<?php

declare(strict_types=1);

namespace RowBuddy\SharedKernel\Exceptions;

/**
 * Generic "aggregate not found" signal a module's application layer can
 * throw without depending on Eloquent's ModelNotFoundException, keeping
 * Domain/Application layers persistence-agnostic.
 */
class NotFoundException extends DomainException {}
