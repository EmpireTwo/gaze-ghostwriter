@props(['paginator'])

<div data-testid="pagination">
    {{ $paginator->links() }}
</div>
