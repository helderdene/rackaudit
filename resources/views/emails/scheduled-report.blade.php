<x-mail::message>
# {{ $reportName }}

Your scheduled report has been generated and is attached to this email.

**Report Details:**
- **Report Type:** {{ $reportType }}
- **Format:** {{ $format }}
- **Generated At:** {{ $generatedAt }}
- **Filters Applied:** {{ $filterDescription }}

Please review the attached report for the latest data.

<x-mail::button :url="config('app.url')">
Open Application
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
