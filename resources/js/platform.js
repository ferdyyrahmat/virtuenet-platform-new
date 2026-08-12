const requestType = document.querySelector('#request-type')

function syncRequestSections() {
    document.querySelectorAll('[data-request-section]').forEach((section) => {
        const visible = section.dataset.requestSection === requestType?.value
        section.hidden = !visible
        section.querySelectorAll('input, select, textarea').forEach((field) => {
            field.disabled = !visible
        })
    })
}

requestType?.addEventListener('change', syncRequestSections)
syncRequestSections()

document.addEventListener('submit', (event) => {
    const message = event.target.dataset.confirm
    if (message && !window.confirm(message)) {
        event.preventDefault()
        event.stopImmediatePropagation()
    }
}, true)

document.querySelector('[data-copy-sensitive]')?.addEventListener('click', async (event) => {
    const value = document.querySelector('[data-sensitive-value]')?.textContent?.trim()
    if (!value) return
    await navigator.clipboard.writeText(value)
    event.currentTarget.textContent = 'Copied'
    window.setTimeout(() => event.currentTarget.textContent = 'Copy', 1500)
})

document.querySelector('[data-reveal-key]')?.addEventListener('click', async (event) => {
    const button = event.currentTarget
    button.disabled = true
    try {
        const response = await fetch(button.dataset.url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
        })
        const payload = await response.json()
        if (!response.ok || !payload.success) throw new Error(payload.message || 'The key could not be revealed.')

        const container = button.closest('[data-key-delivery]')
        const code = document.createElement('code')
        const copy = document.createElement('button')
        code.textContent = payload.key
        copy.type = 'button'
        copy.className = 'btn btn-sm btn-light'
        copy.textContent = 'Copy'
        copy.addEventListener('click', async () => {
            await navigator.clipboard.writeText(payload.key)
            copy.textContent = 'Copied'
        })
        container.replaceChildren(code, copy)
    } catch (error) {
        button.disabled = false
        button.textContent = error.message
    }
})

const usagePage = document.querySelector('[data-ai-usage-url]')

if (usagePage && document.querySelector('[data-ai-logs]')) {
    const number = new Intl.NumberFormat(undefined, { maximumFractionDigits: 2 })
    const compact = new Intl.NumberFormat(undefined, { notation: 'compact', maximumFractionDigits: 1 })
    const money = (value) => `$${number.format(Number(value || 0))}`
    const list = (value) => Array.isArray(value) ? value : Object.values(value || {})
    let loading = false

    const setMetric = (name, value, format = number.format) => {
        const element = document.querySelector(`[data-ai-metric="${name}"]`)
        if (element) element.textContent = value === null || value === undefined ? '—' : format(Number(value))
    }

    const appendCell = (row, value, className = '') => {
        const cell = document.createElement('td')
        cell.className = className
        cell.textContent = String(value ?? '—')
        row.appendChild(cell)
        return cell
    }

    const renderLogs = (value) => {
        const logs = list(value)
        const body = document.querySelector('[data-ai-logs]')
        body.replaceChildren()
        document.querySelector('[data-ai-log-count]').textContent = `${logs.length} requests in selected period`

        if (logs.length === 0) {
            const row = document.createElement('tr')
            const cell = appendCell(row, 'No gateway activity in this period.', 'text-center text-muted py-4')
            cell.colSpan = 6
            body.appendChild(row)
            return
        }

        logs.slice(0, 50).forEach((log) => {
            const row = document.createElement('tr')
            const timestamp = log.startTime || log.start_time || log.created_at
            const parsedTime = timestamp ? new Date(timestamp) : null
            appendCell(row, parsedTime && !Number.isNaN(parsedTime.valueOf()) ? parsedTime.toLocaleString() : timestamp)
            appendCell(row, log.model || log.model_group)
            appendCell(row, log.request_id || log.requestId)
            appendCell(row, number.format(Number(log.total_tokens || Number(log.prompt_tokens || log.input_tokens || 0) + Number(log.completion_tokens || log.output_tokens || 0))))
            appendCell(row, money(log.spend ?? log.response_cost))
            const status = String(log.status || log.status_code || (log.failure_reason ? 'Failed' : 'Completed'))
            const cell = appendCell(row, '')
            const badge = document.createElement('span')
            badge.className = `badge bg-${/fail|error|4\d\d|5\d\d/i.test(status) ? 'danger' : 'success'}-subtle text-${/fail|error|4\d\d|5\d\d/i.test(status) ? 'danger' : 'success'}`
            badge.textContent = status
            cell.appendChild(badge)
            body.appendChild(row)
        })
    }

    const renderModels = (value) => {
        const models = new Map()
        list(value).forEach((log) => {
            const model = log.model || log.model_group || 'Unknown model'
            const current = models.get(model) || { requests: 0, spend: 0 }
            current.requests += 1
            current.spend += Number(log.spend ?? log.response_cost ?? 0)
            models.set(model, current)
        })
        const rows = [...models.entries()].sort((a, b) => b[1].requests - a[1].requests).slice(0, 7)
        const container = document.querySelector('[data-ai-models]')
        container.replaceChildren()
        if (!rows.length) {
            container.innerHTML = '<div class="empty-state compact">No model activity in this period.</div>'
            return
        }
        const maximum = rows[0][1].requests
        rows.forEach(([model, totals]) => {
            const row = document.createElement('div')
            row.className = 'model-row'
            const heading = document.createElement('div')
            const label = document.createElement('strong')
            const meta = document.createElement('small')
            label.textContent = model
            meta.textContent = `${totals.requests} requests · ${money(totals.spend)}`
            heading.append(label, meta)
            const bar = document.createElement('div')
            bar.className = 'model-bar'
            const fill = document.createElement('i')
            fill.style.width = `${(totals.requests / maximum) * 100}%`
            bar.appendChild(fill)
            row.append(heading, bar)
            container.appendChild(row)
        })
    }

    const renderTrend = (dailyValue, logValue) => {
        const points = new Map()
        list(dailyValue).forEach((item) => {
            const date = item.date || item.day || item.start_date
            if (!date) return
            const metrics = item.metrics || item
            points.set(date, {
                spend: Number(metrics.spend ?? metrics.daily_spend ?? metrics.total_spend ?? 0),
                requests: Number(metrics.requests ?? metrics.api_requests ?? metrics.total_requests ?? 0),
            })
        })
        if (!points.size) {
            list(logValue).forEach((log) => {
                const timestamp = log.startTime || log.start_time || log.created_at
                if (!timestamp) return
                const date = String(timestamp).slice(0, 10)
                const point = points.get(date) || { spend: 0, requests: 0 }
                point.spend += Number(log.spend ?? log.response_cost ?? 0)
                point.requests += 1
                points.set(date, point)
            })
        }
        const rows = [...points.entries()].sort(([a], [b]) => a.localeCompare(b)).slice(-14)
        const container = document.querySelector('[data-ai-trend]')
        container.replaceChildren()
        if (!rows.length) {
            container.innerHTML = '<div class="empty-state compact">No daily usage in this period.</div>'
            return
        }
        const maximum = Math.max(...rows.map(([, point]) => point.spend || point.requests), 1)
        rows.forEach(([date, point]) => {
            const row = document.createElement('div')
            row.className = 'usage-trend-row'
            const label = document.createElement('strong')
            label.textContent = new Date(`${date}T00:00:00`).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
            const bar = document.createElement('div')
            bar.className = 'usage-bar'
            const fill = document.createElement('i')
            fill.style.width = `${((point.spend || point.requests) / maximum) * 100}%`
            bar.appendChild(fill)
            const spend = document.createElement('small')
            spend.textContent = money(point.spend)
            const requests = document.createElement('small')
            requests.textContent = `${point.requests} req`
            row.append(label, bar, spend, requests)
            container.appendChild(row)
        })
    }

    const updateBudget = (spend) => {
        const budget = Number(usagePage.dataset.aiBudget || 0)
        const percentage = budget > 0 ? Math.min(100, (Number(spend || 0) / budget) * 100) : 0
        document.querySelector('[data-ai-budget-percent]').textContent = `${number.format(percentage)}%`
        document.querySelector('[data-ai-budget-bar]').style.width = `${percentage}%`
        document.querySelector('[data-ai-budget-caption]').textContent = `${money(spend)} of ${money(budget)}`
    }

    const loadUsage = async () => {
        if (loading) return
        loading = true
        const refresh = document.querySelector('[data-ai-refresh]')
        const updated = document.querySelector('[data-ai-updated]')
        refresh.disabled = true
        try {
            const days = document.querySelector('[data-ai-days]')?.value || 30
            const response = await fetch(`${usagePage.dataset.aiUsageUrl}?days=${days}`, { headers: { Accept: 'application/json' } })
            const payload = await response.json()
            if (!response.ok || payload.success === false) throw new Error(payload.message || 'Gateway data is temporarily unavailable.')
            const data = payload.data || {}
            const metrics = data.metrics || {}
            setMetric('spend', metrics.spend, money)
            setMetric('requests', metrics.requests)
            setMetric('total_tokens', metrics.total_tokens, compact.format)
            setMetric('input_tokens', metrics.input_tokens, compact.format)
            setMetric('output_tokens', metrics.output_tokens, compact.format)
            setMetric('models', metrics.models)
            setMetric('success_rate', metrics.success_rate, (value) => `${number.format(value)}%`)
            updateBudget(metrics.spend)
            renderTrend(data.daily, data.logs)
            renderModels(data.logs)
            renderLogs(data.logs)
            updated.textContent = `Updated ${new Date(data.fetched_at || Date.now()).toLocaleTimeString()}`
        } catch (error) {
            updated.textContent = error.message
        } finally {
            refresh.disabled = false
            loading = false
        }
    }

    document.querySelector('[data-ai-days]')?.addEventListener('change', loadUsage)
    document.querySelector('[data-ai-refresh]')?.addEventListener('click', loadUsage)
    loadUsage()
    window.setInterval(() => !document.hidden && loadUsage(), 30000)
}
