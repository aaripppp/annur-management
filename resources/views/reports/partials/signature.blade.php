<table class="signature-table" style="table-layout: fixed;">
    <tr class="signature-heading-row">
        <td>
            <div class="signature-role" style="line-height: 1.2; word-wrap: break-word;">Menyetujui,<br>{{ $approval['approver_title'] }}</div>
        </td>
        <td>
            <div class="signature-role" style="line-height: 1.2; word-wrap: break-word;">Mengetahui,<br>{{ $approval['reviewer_title'] }}</div>
        </td>
        <td>
            <div class="signature-role" style="line-height: 1.2; word-wrap: break-word;">{{ $approval['city_and_date'] }}<br>{{ $approval['report_creator_title'] }}</div>
        </td>
    </tr>
    <tr class="signature-spacer-row">
        <td><div class="signature-space"></div></td>
        <td><div class="signature-space"></div></td>
        <td><div class="signature-space"></div></td>
    </tr>
    <tr class="signature-name-row">
        <td><div class="signature-name" style="width: 80%; max-width: 130px; line-height: 1.2; word-wrap: break-word;">{{ $approval['approver_name'] }}</div></td>
        <td><div class="signature-name" style="width: 80%; max-width: 130px; line-height: 1.2; word-wrap: break-word;">{{ $approval['reviewer_name'] }}</div></td>
        <td><div class="signature-name" style="width: 80%; max-width: 130px; line-height: 1.2; word-wrap: break-word;">{{ $approval['report_creator_name'] }}</div></td>
    </tr>
</table>
