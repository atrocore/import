<table class="table">
    <thead>
        <tr>
            <td class="col-xs-4 col-sm-5">Key</td>
            <td class="col-xs-6">Value</td>
            <td class="col-xs-2 col-sm-1"></td>
        </tr>
    </thead>
    <tbody>
    {{#each headers}}
        <tr data-key="{{@index}}"></tr>
    {{/each}}
    </tbody>
    <tfoot>
        <tr>
            <td><button class="btn" data-action="add-header">Add header</button></td>
            <td></td>
            <td></td>
        </tr>
    </tfoot>
</table>

