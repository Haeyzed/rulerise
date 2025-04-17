<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

/**
 * SearchServiceProvider
 *
 * This service provider extends the query builder to add a 'search' method
 * that allows for easy searching across multiple columns and relationships.
 * The 'search' method will apply the search term to multiple attributes of
 * a model or its related models, making it easier to perform complex searches
 * in an elegant and reusable manner.
 */
class SearchServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * This method is used to bind services into the container. In this case,
     * no specific service is registered, but this method is required by the
     * ServiceProvider base class.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * This method is used to define custom query builder macros. In this case,
     * it adds a 'search' method to the Builder class that allows for searching
     * across multiple attributes of a model, including its relationships.
     *
     * @return void
     */
    public function boot(): void
    {
        /**
         * Add a 'search' macro to the query builder.
         *
         * This method enables searching across multiple columns and relationships.
         * It applies the search term to each specified attribute using the specified
         * search options.
         *
         * @param array|string $attributes The list of attributes (columns or relationships)
         *                                 to search across.
         * @param string|array $searchTerm The search term(s) to match against the attributes.
         * @param array $options Additional search options:
         *                      - 'caseSensitive' (bool): Whether the search should be case sensitive (default: false)
         *                      - 'matchType' (string): Type of match to perform ('contains', 'startsWith', 'endsWith', 'exact') (default: 'contains')
         *                      - 'operator' (string): SQL operator to use ('LIKE', '=', '>', '<', etc.) (default: 'LIKE')
         *                      - 'boolean' (string): Boolean operator to use between conditions ('and', 'or') (default: 'or')
         *
         * @return Builder The query builder instance, allowing for method chaining.
         * @throws InvalidArgumentException If invalid options are provided
         */
        Builder::macro('search', function ($attributes, $searchTerm, array $options = []) {
            // Normalize attributes to array
            $attributes = Arr::wrap($attributes);

            if (empty($attributes)) {
                return $this;
            }

            // Default options
            $options = array_merge([
                'caseSensitive' => false,
                'matchType' => 'contains',
                'operator' => 'LIKE',
                'boolean' => 'or',
            ], $options);

            // Validate match type
            $validMatchTypes = ['contains', 'startsWith', 'endsWith', 'exact'];
            if (!in_array($options['matchType'], $validMatchTypes)) {
                throw new InvalidArgumentException("Invalid match type. Must be one of: " . implode(', ', $validMatchTypes));
            }

            // Validate boolean operator
            $validBooleans = ['and', 'or'];
            if (!in_array(strtolower($options['boolean']), $validBooleans)) {
                throw new InvalidArgumentException("Invalid boolean operator. Must be one of: " . implode(', ', $validBooleans));
            }

            // Normalize search terms to array
            $searchTerms = Arr::wrap($searchTerm);

            // If no search terms, return the query as is
            if (empty($searchTerms)) {
                return $this;
            }

            $boolean = strtolower($options['boolean']) === 'and' ? 'and' : 'or';

            $this->where(function (Builder $query) use ($attributes, $searchTerms, $options, $boolean) {
                foreach ($searchTerms as $term) {
                    $query->where(function (Builder $subQuery) use ($attributes, $term, $options) {
                        foreach ($attributes as $attribute) {
                            $this->buildSearchQuery($subQuery, $attribute, $term, $options);
                        }
                    }, null, null, $boolean);
                }
            });

            return $this;
        });

        /**
         * Add a helper method to build the search query for a specific attribute and term.
         *
         * @param Builder $query The query builder instance
         * @param string $attribute The attribute to search on
         * @param string $term The search term
         * @param array $options The search options
         *
         * @return void
         */
        Builder::macro('buildSearchQuery', function (Builder $query, string $attribute, string $term, array $options) {
            // Format the search term based on match type
            $formattedTerm = $this->formatSearchTerm($term, $options);

            if (str_contains($attribute, '.')) {
                // Handle searching through relationships
                $relationParts = explode('.', $attribute);
                $relation = implode('.', array_slice($relationParts, 0, -1));
                $column = end($relationParts);

                $query->orWhereHas($relation, function (Builder $relationQuery) use ($column, $formattedTerm, $options) {
                    $this->applySearchCondition($relationQuery, $column, $formattedTerm, $options);
                });
            } else {
                // Handle searching through model's own columns
                $this->applySearchCondition($query, $attribute, $formattedTerm, $options, 'or');
            }
        });

        /**
         * Add a helper method to format the search term based on match type.
         *
         * @param string $term The search term
         * @param array $options The search options
         *
         * @return string The formatted search term
         */
        Builder::macro('formatSearchTerm', function (string $term, array $options) {
            if ($options['operator'] !== 'LIKE') {
                return $term;
            }

            return match ($options['matchType']) {
                'startsWith' => $term . '%',
                'endsWith' => '%' . $term,
                'exact' => $term,
                default => '%' . $term . '%',
            };
        });

        /**
         * Add a helper method to apply the search condition to a query.
         *
         * @param Builder $query The query builder instance
         * @param string $column The column to search on
         * @param string $term The formatted search term
         * @param array $options The search options
         * @param string $boolean The boolean operator to use
         *
         * @return void
         */
        Builder::macro('applySearchCondition', function (Builder $query, string $column, string $term, array $options, string $boolean = 'and') {
            $operator = $options['operator'];

            if (!$options['caseSensitive'] && $operator === 'LIKE') {
                $query->whereRaw("LOWER({$column}) {$operator} ?", [strtolower($term)], $boolean);
            } else {
                $query->where($column, $operator, $term, $boolean);
            }
        });
    }
}
