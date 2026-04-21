const API_URL = "../api/trades.php";
const AUTH_URL = "../api/auth.php";

const logoutButton = document.getElementById("logoutButton");

const tradeForm = document.getElementById("tradeForm");
const tradeIdInput = document.getElementById("tradeId");
const assetInput = document.getElementById("asset");
const typeInput = document.getElementById("type");
const quantityInput = document.getElementById("quantity");
const priceInput = document.getElementById("price");
const tradeDateInput = document.getElementById("tradeDate");
const formTitle = document.getElementById("formTitle");
const saveButton = document.getElementById("saveButton");
const cancelEditButton = document.getElementById("cancelEditButton");

const buyTotalEl = document.getElementById("buyTotal");
const sellTotalEl = document.getElementById("sellTotal");
const netProfitEl = document.getElementById("netProfit");
const tradeTableBody = document.getElementById("tradeTableBody");
const realizedTableBody = document.getElementById("realizedTableBody");

const assetFilterInput = document.getElementById("assetFilter");
const searchFilterInput = document.getElementById("searchFilter");
const fromDateFilterInput = document.getElementById("fromDateFilter");
const toDateFilterInput = document.getElementById("toDateFilter");
const applyFilterButton = document.getElementById("applyFilterButton");
const clearFilterButton = document.getElementById("clearFilterButton");
const exportCsvButton = document.getElementById("exportCsvButton");
const prevPageButton = document.getElementById("prevPageButton");
const nextPageButton = document.getElementById("nextPageButton");
const paginationInfo = document.getElementById("paginationInfo");
const pageSizeSelect = document.getElementById("pageSizeSelect");

const peso = new Intl.NumberFormat("en-PH", { style: "currency", currency: "PHP" });
let chart;
let currentFilters = { asset: "", search: "", from_date: "", to_date: "" };
let currentPage = 1;
let pageSize = 10;
let lastTrades = [];

function buildQuery(params) {
    const query = new URLSearchParams();
    Object.entries(params).forEach(([key, value]) => {
        if (value !== "" && value !== null && value !== undefined) query.set(key, String(value));
    });
    return query.toString() ? `?${query.toString()}` : "";
}

async function apiRequest(url, options = {}) {
    const response = await fetch(url, options);
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(data.error || "Request failed.");
    return data;
}

async function fetchTrades() {
    const params = {
        ...currentFilters,
        page: currentPage,
        page_size: pageSize
    };
    return apiRequest(`${API_URL}${buildQuery(params)}`);
}

function setSummary(totals) {
    const buyTotal = Number(totals.buy_total || 0);
    const sellTotal = Number(totals.sell_total || 0);
    const netProfit = Number(totals.net_profit || 0);
    buyTotalEl.textContent = peso.format(buyTotal);
    sellTotalEl.textContent = peso.format(sellTotal);
    netProfitEl.textContent = peso.format(netProfit);
    netProfitEl.className = netProfit >= 0 ? "profit-positive" : "profit-negative";
}

function renderTrades(trades) {
    if (!trades.length) {
        tradeTableBody.innerHTML = "<tr><td colspan='8'>No trades found.</td></tr>";
        return;
    }
    tradeTableBody.innerHTML = "";
    trades.forEach((trade) => {
        const tr = document.createElement("tr");
        tr.innerHTML = `
            <td>${trade.id}</td>
            <td>${trade.asset}</td>
            <td class="${trade.type === "buy" ? "text-buy" : "text-sell"}">${trade.type.toUpperCase()}</td>
            <td>${trade.quantity}</td>
            <td>${peso.format(Number(trade.price))}</td>
            <td>${peso.format(Number(trade.total || 0))}</td>
            <td>${trade.trade_date}</td>
            <td>
                <button type="button" data-action="edit" data-id="${trade.id}">Edit</button>
                <button type="button" class="secondary" data-action="delete" data-id="${trade.id}">Delete</button>
            </td>
        `;
        tradeTableBody.appendChild(tr);
    });
}

function renderRealizedTable(rows) {
    if (!rows.length) {
        realizedTableBody.innerHTML = "<tr><td colspan='4'>No data.</td></tr>";
        return;
    }
    realizedTableBody.innerHTML = "";
    rows.forEach((row) => {
        const tr = document.createElement("tr");
        const pl = Number(row.realized_pl || 0);
        tr.innerHTML = `
            <td>${row.asset}</td>
            <td>${row.sold_qty}</td>
            <td>${row.open_qty}</td>
            <td class="${pl >= 0 ? "profit-positive" : "profit-negative"}">${peso.format(pl)}</td>
        `;
        realizedTableBody.appendChild(tr);
    });
}

function renderChart(profitSeries) {
    const labels = profitSeries.map((point, index) => `${point.trade_date} #${index + 1}`);
    const values = profitSeries.map((point) => Number(point.profit));
    const data = {
        labels,
        datasets: [{
            label: "Running Profit (PHP)",
            data: values,
            borderColor: "#38bdf8",
            backgroundColor: "rgba(56, 189, 248, 0.15)",
            borderWidth: 2,
            tension: 0.25,
            fill: true
        }]
    };
    if (chart) {
        chart.data = data;
        chart.update();
        return;
    }
    chart = new Chart(document.getElementById("profitChart"), {
        type: "line",
        data,
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: { y: { ticks: { callback: (value) => peso.format(Number(value)) } } },
            plugins: { legend: { labels: { color: "#e2e8f0" } } }
        }
    });
}

function setPagination(pagination) {
    const page = Number(pagination.page || 1);
    const totalPages = Number(pagination.total_pages || 1);
    paginationInfo.textContent = `Page ${page} of ${totalPages} (${pagination.total_count || 0} trades)`;
    prevPageButton.disabled = page <= 1;
    nextPageButton.disabled = page >= totalPages;
}

function setEditMode(trade) {
    tradeIdInput.value = trade.id;
    assetInput.value = trade.asset;
    typeInput.value = trade.type;
    quantityInput.value = trade.quantity;
    priceInput.value = trade.price;
    tradeDateInput.value = trade.trade_date;
    formTitle.textContent = "Edit Trade";
    saveButton.textContent = "Update Trade";
    cancelEditButton.classList.remove("hidden");
}

function resetForm() {
    tradeForm.reset();
    tradeIdInput.value = "";
    formTitle.textContent = "Add Trade";
    saveButton.textContent = "Add Trade";
    cancelEditButton.classList.add("hidden");
    tradeDateInput.valueAsDate = new Date();
}

async function loadAndRender() {
    const data = await fetchTrades();
    lastTrades = data.trades || [];
    renderTrades(lastTrades);
    setSummary(data.totals || {});
    renderRealizedTable(data.realized_pl_by_asset || []);
    renderChart(data.profit_series || []);
    setPagination(data.pagination || {});
}

async function createTrade(payload) {
    await apiRequest(API_URL, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
    });
}

async function updateTrade(id, payload) {
    await apiRequest(`${API_URL}?id=${encodeURIComponent(id)}`, {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
    });
}

async function deleteTrade(id) {
    await apiRequest(`${API_URL}?id=${encodeURIComponent(id)}`, { method: "DELETE" });
}

logoutButton.addEventListener("click", async () => {
    try {
        await apiRequest(`${AUTH_URL}?action=logout`, { method: "POST" });
        window.location.href = "./login.php";
    } catch (error) {
        alert(error.message);
    }
});

tradeForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    const payload = {
        asset: assetInput.value.trim(),
        type: typeInput.value,
        quantity: Number(quantityInput.value),
        price: Number(priceInput.value),
        trade_date: tradeDateInput.value
    };
    try {
        if (tradeIdInput.value) {
            await updateTrade(tradeIdInput.value, payload);
        } else {
            await createTrade(payload);
        }
        resetForm();
        await loadAndRender();
    } catch (error) {
        alert(error.message);
    }
});

cancelEditButton.addEventListener("click", () => resetForm());

tradeTableBody.addEventListener("click", async (event) => {
    const target = event.target;
    if (!(target instanceof HTMLButtonElement)) return;
    const id = target.dataset.id;
    const action = target.dataset.action;
    if (!id || !action) return;

    if (action === "delete") {
        if (!confirm(`Delete trade #${id}?`)) return;
        try {
            await deleteTrade(id);
            await loadAndRender();
        } catch (error) {
            alert(error.message);
        }
        return;
    }

    if (action === "edit") {
        const trade = lastTrades.find((row) => String(row.id) === id);
        if (trade) setEditMode(trade);
    }
});

applyFilterButton.addEventListener("click", async () => {
    currentPage = 1;
    currentFilters = {
        asset: assetFilterInput.value.trim(),
        search: searchFilterInput.value.trim(),
        from_date: fromDateFilterInput.value,
        to_date: toDateFilterInput.value
    };
    try {
        await loadAndRender();
    } catch (error) {
        alert(error.message);
    }
});

clearFilterButton.addEventListener("click", async () => {
    assetFilterInput.value = "";
    searchFilterInput.value = "";
    fromDateFilterInput.value = "";
    toDateFilterInput.value = "";
    currentPage = 1;
    currentFilters = { asset: "", search: "", from_date: "", to_date: "" };
    try {
        await loadAndRender();
    } catch (error) {
        alert(error.message);
    }
});

exportCsvButton.addEventListener("click", () => {
    const query = buildQuery({ ...currentFilters, export: "csv" });
    window.location.href = `${API_URL}${query}`;
});

prevPageButton.addEventListener("click", async () => {
    if (currentPage <= 1) return;
    currentPage -= 1;
    try {
        await loadAndRender();
    } catch (error) {
        alert(error.message);
    }
});

nextPageButton.addEventListener("click", async () => {
    currentPage += 1;
    try {
        await loadAndRender();
    } catch (error) {
        currentPage = Math.max(1, currentPage - 1);
        alert(error.message);
    }
});

pageSizeSelect.addEventListener("change", async () => {
    pageSize = Number(pageSizeSelect.value || 10);
    currentPage = 1;
    try {
        await loadAndRender();
    } catch (error) {
        alert(error.message);
    }
});

resetForm();
loadAndRender().catch((error) => alert(error.message));
