<table class="table">
    <thead>
        <tr>
            <td class="col-xs-4 col-sm-5">Key</td>
            <td class="col-xs-7">Value</td>
            <td></td>
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

