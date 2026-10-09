<table class="data-table">
    <thead>
        <tr>
            <th>Sr.#</th>
            @foreach($columns as $col)
                <th>{{ str_replace('_', ' ', ucwords($col)) }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse($rows as $i => $row)
            <tr>
                <td>{{ $i + 1 }}</td>
                @foreach($columns as $col)
                    <td>{{ is_array($row) ? ($row[$col] ?? '') : ($row->{$col} ?? '') }}</td>
                @endforeach
            </tr>
        @empty
            <tr class="nil-row"><td colspan="{{ count($columns) + 1 }}">NIL</td></tr>
        @endforelse
    </tbody>
</table>