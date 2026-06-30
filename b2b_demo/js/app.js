(function () {
    const STORAGE_KEY = "eb_b2b_demo_v1";
    const ROUTES = ["inicio", "catalogo", "carrito", "pedidos", "presupuesto", "condiciones", "administrador", "admin-analisis", "admin-listas", "admin-condiciones", "admin-productos"];
    const FINAL_STATUS = "Facturado y despachado";
    const STATUS_ALIAS = {
        "Enviado al ERP": "Recepcionado Siesa",
        "Remisionado": "En alistamiento",
        "Facturado": FINAL_STATUS
    };
    const state = {
        data: null,
        currentRoute: "inicio",
        catalogSearch: "",
        catalogLine: "Todas",
        charts: {
            dashboardBudget: null,
            dashboardService: null,
            budgetMain: null,
            budgetByLine: null,
            adminPortfolio: null,
            adminDistributorBudget: null,
            adminDistributorLine: null
        }
    };

    const el = {
        loginView: document.getElementById("loginView"),
        appView: document.getElementById("appView"),
        loginForm: document.getElementById("loginForm"),
        loginError: document.getElementById("loginError"),
        distributorName: document.getElementById("distributorName"),
        cartCounter: document.getElementById("cartCounter"),
        cartButton: document.getElementById("cartButton"),
        logoutBtn: document.getElementById("logoutBtn"),
        resetDemoBtn: document.getElementById("resetDemoBtn"),
        nav: document.getElementById("mainNav"),
        toast: document.getElementById("toast"),
        drawer: document.getElementById("orderDrawer"),
        drawerBody: document.getElementById("orderDrawerBody"),
        closeDrawerBtn: document.getElementById("closeDrawerBtn")
    };

    function clone(value) {
        return JSON.parse(JSON.stringify(value));
    }

    function readStorage() {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) {
            return null;
        }

        try {
            return JSON.parse(raw);
        } catch (error) {
            return null;
        }
    }

    function writeStorage(next) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(next));
    }

    function buildSeedState() {
        const distributors = clone(window.DEMO_SEED.distributors).map(function (d) {
            const creditLimit = toNumber(d.creditLimit);
            const monthlyBudgetGoal = Math.max(toNumber(d.monthlyBudgetGoal), creditLimit);
            return {
                id: d.id,
                username: d.username,
                password: d.password,
                name: d.name,
                nit: d.nit,
                city: d.city,
                zone: d.zone,
                priceList: d.priceList,
                creditLimit: creditLimit,
                portfolioBalance: d.portfolioBalance,
                overdueBalance: d.overdueBalance,
                monthlyBudgetGoal: monthlyBudgetGoal,
                monthlyExecutedBase: d.monthlyExecutedBase,
                serviceKpi: d.serviceKpi,
                isActive: typeof d.isActive === "boolean" ? d.isActive : true,
                conditions: d.conditions,
                categoryExecutionBase: d.categoryExecutionBase
            };
        });

        const priceListOptions = Array.from(new Set(distributors.map(function (d) { return d.priceList; })));
        const priceMatrixByList = priceListOptions.reduce(function (acc, listName) {
            acc[listName] = {};
            window.DEMO_SEED.products.forEach(function (product) {
                acc[listName][product.sku] = product.price;
            });
            return acc;
        }, {});

        return {
            session: null,
            products: clone(window.DEMO_SEED.products),
            lines: clone(window.DEMO_SEED.lines),
            distributors: distributors,
            ordersByDistributor: clone(window.DEMO_SEED.seededOrdersByDistributor),
            cartByDistributor: {
                bella: [],
                cali: []
            },
            orderSequence: {
                bella: 1042,
                cali: 2048
            },
            adminConfig: {
                priceListOptions: priceListOptions,
                selectedDistributorId: distributors[0] ? distributors[0].id : null,
                selectedPriceList: priceListOptions[0] || null,
                priceMatrixByList: priceMatrixByList
            }
        };
    }

    function initState() {
        const stored = readStorage();
        state.data = stored || buildSeedState();

        state.data.distributors.forEach(function (distributor) {
            distributor.creditLimit = toNumber(distributor.creditLimit);
            distributor.monthlyBudgetGoal = Math.max(toNumber(distributor.monthlyBudgetGoal), distributor.creditLimit);
        });

        Object.keys(state.data.ordersByDistributor || {}).forEach(function (distributorId) {
            const orders = state.data.ordersByDistributor[distributorId] || [];
            orders.forEach(function (order) {
                order.status = normalizeOrderStatus(order.status);
                order.dispatchType = order.dispatchType || (order.status === FINAL_STATUS ? "completo" : null);
                order.adminObservation = order.adminObservation || "";
                order.timeline = Array.isArray(order.timeline) ? order.timeline : [];

                order.timeline = order.timeline.map(function (entry) {
                    return {
                        status: normalizeOrderStatus(entry.status),
                        at: entry.at || order.createdAt
                    };
                });

                if (!order.timeline.length) {
                    order.timeline.push({ status: "Registrado", at: order.createdAt });
                }
            });
        });

        state.data.products.forEach(function (product) {
            if (!product.image) {
                const seedProduct = window.DEMO_SEED.products.find(function (p) { return p.sku === product.sku; });
                if (seedProduct && seedProduct.image) {
                    product.image = seedProduct.image;
                }
            }
            if (typeof product.isActive !== "boolean") {
                product.isActive = true;
            }
        });

        writeStorage(state.data);
    }

    function money(value) {
        return "$" + Math.round(value).toLocaleString("es-CO");
    }

    function percent(value) {
        return Math.round(value) + "%";
    }

    function toNumber(value) {
        const numeric = Number(value);
        return Number.isFinite(numeric) ? numeric : 0;
    }

    function normalizeText(value) {
        return String(value || "")
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .toLowerCase();
    }

    function normalizeOrderStatus(status) {
        return STATUS_ALIAS[status] || status;
    }

    function getAdminConfig() {
        if (!state.data.adminConfig) {
            const fallbackOptions = Array.from(new Set(state.data.distributors.map(function (d) { return d.priceList; })));
            const defaultMatrix = fallbackOptions.reduce(function (acc, listName) {
                acc[listName] = {};
                state.data.products.forEach(function (product) {
                    acc[listName][product.sku] = product.price;
                });
                return acc;
            }, {});
            state.data.adminConfig = {
                priceListOptions: fallbackOptions.length ? fallbackOptions : ["Mayorista A", "Mayorista B"],
                selectedDistributorId: state.data.distributors[0] ? state.data.distributors[0].id : null,
                selectedPriceList: fallbackOptions[0] || null,
                priceMatrixByList: defaultMatrix
            };
            writeStorage(state.data);
        }

        const adminConfig = state.data.adminConfig;
        adminConfig.priceListOptions = adminConfig.priceListOptions || [];
        adminConfig.priceMatrixByList = adminConfig.priceMatrixByList || {};

        adminConfig.priceListOptions.forEach(function (listName) {
            if (!adminConfig.priceMatrixByList[listName]) {
                adminConfig.priceMatrixByList[listName] = {};
            }

            state.data.products.forEach(function (product) {
                if (!Number.isFinite(Number(adminConfig.priceMatrixByList[listName][product.sku]))) {
                    adminConfig.priceMatrixByList[listName][product.sku] = product.price;
                }
            });
        });

        if (!adminConfig.selectedPriceList || !adminConfig.priceListOptions.includes(adminConfig.selectedPriceList)) {
            adminConfig.selectedPriceList = adminConfig.priceListOptions[0] || null;
        }

        writeStorage(state.data);

        return adminConfig;
    }

    function getPriceForListSku(listName, sku) {
        const adminConfig = getAdminConfig();
        const listPrices = adminConfig.priceMatrixByList[listName] || {};
        const listPrice = Number(listPrices[sku]);

        if (Number.isFinite(listPrice) && listPrice >= 0) {
            return listPrice;
        }

        const product = state.data.products.find(function (item) { return item.sku === sku; });
        return product ? product.price : 0;
    }

    function getProductPriceForDistributor(product, distributor) {
        if (!product || !distributor) {
            return 0;
        }
        return getPriceForListSku(distributor.priceList, product.sku);
    }

    function syncCartPricesByDistributor(distributor) {
        if (!distributor) {
            return;
        }

        const cart = clone(state.data.cartByDistributor[distributor.id] || []);
        let hasChanges = false;

        cart.forEach(function (line) {
            const expectedPrice = getPriceForListSku(distributor.priceList, line.sku);
            if (line.price !== expectedPrice) {
                line.price = expectedPrice;
                line.subtotal = expectedPrice * line.quantity;
                hasChanges = true;
            }
        });

        if (hasChanges) {
            state.data.cartByDistributor[distributor.id] = cart;
            writeStorage(state.data);
        }
    }

    function currentDistributor() {
        if (!isDistributorSession()) {
            return null;
        }

        return state.data.distributors.find(function (d) {
            return d.id === state.data.session.distributorId;
        }) || null;
    }
    function getDistributorById(distributorId) {
        return state.data.distributors.find(function (item) {
            return item.id === distributorId;
        }) || null;
    }

    function currentSession() {
        return state.data.session || null;
    }

    function isAdminSession() {
        return Boolean(currentSession() && currentSession().role === "admin");
    }

    function isDistributorSession() {
        return Boolean(currentSession() && currentSession().role === "distributor");
    }

    function currentCart() {
        const distributor = currentDistributor();
        if (!distributor) {
            return [];
        }

        return state.data.cartByDistributor[distributor.id] || [];
    }

    function setCurrentCart(cart) {
        const distributor = currentDistributor();
        if (!distributor) {
            return;
        }

        state.data.cartByDistributor[distributor.id] = cart;
        writeStorage(state.data);
    }

    function currentOrders() {
        const distributor = currentDistributor();
        if (!distributor) {
            return [];
        }

        const orders = state.data.ordersByDistributor[distributor.id] || [];
        return clone(orders).sort(function (a, b) {
            return new Date(b.createdAt) - new Date(a.createdAt);
        });
        return ordersForDistributor(distributor.id);
    }

    function ordersForDistributor(distributorId) {
        const orders = state.data.ordersByDistributor[distributorId] || [];
        return clone(orders).sort(function (a, b) {
            return new Date(b.createdAt) - new Date(a.createdAt);
        });
    }

    function isFinalStatus(status) {
        return normalizeOrderStatus(status) === FINAL_STATUS;
    }

    function slaCountStart(orderDateIso) {
        const createdAt = new Date(orderDateIso);
        const start = new Date(createdAt);
        if (createdAt.getHours() >= 9) {
            start.setDate(start.getDate() + 1);
        }
        start.setHours(0, 0, 0, 0);
        return start;
    }

    function orderSlaReferenceDate(order) {
        if (!isFinalStatus(order.status)) {
            return new Date();
        }

        const timeline = Array.isArray(order.timeline) ? order.timeline : [];
        const finalStep = timeline.find(function (entry) {
            return normalizeOrderStatus(entry.status) === FINAL_STATUS;
        });

        return finalStep ? new Date(finalStep.at) : new Date(order.createdAt);
    }

    function carteraWeeklyMonthly(distributor) {
        const now = new Date();
        const monthStart = new Date(now.getFullYear(), now.getMonth(), 1);
        const weekStart = new Date(now);
        const day = now.getDay();
        const diffToMonday = day === 0 ? 6 : day - 1;
        weekStart.setDate(now.getDate() - diffToMonday);
        weekStart.setHours(0, 0, 0, 0);

        const orders = ordersForDistributor(distributor.id);

        const weekOrdersTotal = orders
            .filter(function (order) { return new Date(order.createdAt) >= weekStart; })
            .reduce(function (sum, order) { return sum + order.total; }, 0);
        const monthOrdersTotal = orders
            .filter(function (order) { return new Date(order.createdAt) >= monthStart; })
            .reduce(function (sum, order) { return sum + order.total; }, 0);
        const weekOrdersCount = orders.filter(function (order) { return new Date(order.createdAt) >= weekStart; }).length;
        const monthOrdersCount = orders.filter(function (order) { return new Date(order.createdAt) >= monthStart; }).length;

        return {
            weekly: weekOrdersTotal,
            monthly: monthOrdersTotal,
            weekCount: weekOrdersCount,
            monthCount: monthOrdersCount
        };
    }

    function showToast(message) {
        el.toast.textContent = message;
        el.toast.classList.add("show");
        window.setTimeout(function () {
            el.toast.classList.remove("show");
        }, 2200);
    }

    function setupAuth() {
        el.loginForm.addEventListener("submit", function (event) {
            event.preventDefault();
            const formData = new FormData(el.loginForm);
            const username = String(formData.get("username") || "").trim().toLowerCase();
            const password = String(formData.get("password") || "").trim();

            const distributor = state.data.distributors.find(function (d) {
                return d.username === username && d.password === password;
            });

            if (username === window.DEMO_SEED.adminUser.username && password === window.DEMO_SEED.adminUser.password) {
                state.data.session = {
                    role: "admin",
                    distributorId: null,
                    displayName: window.DEMO_SEED.adminUser.name,
                    loggedAt: new Date().toISOString()
                };
                writeStorage(state.data);
                el.loginError.textContent = "";
                window.location.hash = "#/administrador";
                syncUiBySession();
                return;
            }

            if (!distributor) {
                el.loginError.textContent = "Usuario o clave inválidos para la demo.";
                return;
            }

            if (!distributor.isActive) {
                el.loginError.textContent = "Este distribuidor está inactivo. Contacta al administrador.";
                return;
            }

            state.data.session = {
                role: "distributor",
                distributorId: distributor.id,
                displayName: distributor.name,
                loggedAt: new Date().toISOString()
            };
            writeStorage(state.data);
            el.loginError.textContent = "";
            window.location.hash = "#/inicio";
            syncUiBySession();
        });

        el.logoutBtn.addEventListener("click", function () {
            state.data.session = null;
            writeStorage(state.data);
            syncUiBySession();
            showToast("Sesión cerrada.");
        });

        el.resetDemoBtn.addEventListener("click", function () {
            localStorage.removeItem(STORAGE_KEY);
            state.data = buildSeedState();
            writeStorage(state.data);
            state.catalogSearch = "";
            state.catalogLine = "Todas";
            syncUiBySession();
            showToast("Demo reiniciada.");
        });

        el.cartButton.addEventListener("click", function () {
            window.location.hash = "#/carrito";
        });

        el.closeDrawerBtn.addEventListener("click", function () {
            closeDrawer();
        });
    }

    function syncUiBySession() {
        const session = currentSession();

        if (!session) {
            el.loginView.classList.remove("hidden");
            el.appView.classList.add("hidden");
            closeDrawer();
            return;
        }

        el.loginView.classList.add("hidden");
        el.appView.classList.remove("hidden");
        el.distributorName.textContent = session.displayName || "Usuario";
        el.cartButton.classList.toggle("hidden", isAdminSession());
        configureNavForRole();
        updateCartCounter();
        handleRouting();
    }

    function configureNavForRole() {
        Array.from(el.nav.querySelectorAll("[data-role]")).forEach(function (node) {
            const role = node.getAttribute("data-role");
            if (role === "admin") {
                node.classList.toggle("hidden", !isAdminSession());
            } else {
                node.classList.toggle("hidden", !isDistributorSession());
            }
        });
    }

    function updateCartCounter() {
        const productsCount = currentCart().length;
        el.cartCounter.textContent = String(productsCount);
    }

    function routeFromHash() {
        const hash = window.location.hash || "#/inicio";
        const route = hash.replace("#/", "").trim().toLowerCase();
        if (!ROUTES.includes(route)) {
            return "inicio";
        }
        return route;
    }

    function isAdminRoute(route) {
        return route === "administrador" || route === "admin-analisis" || route === "admin-listas" || route === "admin-condiciones" || route === "admin-productos";
    }

    function handleRouting() {
        if (!state.data.session) {
            return;
        }

        state.currentRoute = routeFromHash();

        if (isAdminSession() && !isAdminRoute(state.currentRoute)) {
            state.currentRoute = "administrador";
            window.location.hash = "#/administrador";
        }

        if (isDistributorSession() && isAdminRoute(state.currentRoute)) {
            state.currentRoute = "inicio";
            window.location.hash = "#/inicio";
        }

        ROUTES.forEach(function (route) {
            const section = document.getElementById("view-" + route);
            if (!section) {
                return;
            }
            section.classList.toggle("hidden", route !== state.currentRoute);
        });

        Array.from(el.nav.querySelectorAll("a[data-route]")).forEach(function (anchor) {
            anchor.classList.toggle("active", anchor.getAttribute("data-route") === state.currentRoute);
        });

        if (state.currentRoute === "inicio") {
            renderInicio();
        } else if (state.currentRoute === "catalogo") {
            renderCatalogo();
        } else if (state.currentRoute === "carrito") {
            renderCarrito();
        } else if (state.currentRoute === "pedidos") {
            renderPedidos();
        } else if (state.currentRoute === "presupuesto") {
            renderPresupuesto();
        } else if (state.currentRoute === "condiciones") {
            renderCondiciones();
        } else if (state.currentRoute === "administrador") {
            renderAdministrador();
        } else if (state.currentRoute === "admin-analisis") {
            renderAdminAnalisis();
        } else if (state.currentRoute === "admin-listas") {
            renderAdminListas();
        } else if (state.currentRoute === "admin-condiciones") {
            renderAdminCondiciones();
        } else if (state.currentRoute === "admin-productos") {
            renderAdminProductos();
        }
    }

    function monthlyExecuted() {
        const distributor = currentDistributor();
        if (!distributor) {
            return 0;
        }

        return monthlyExecutedForDistributor(distributor);
    }

    function monthlyExecutedForDistributor(distributor) {
        const orders = state.data.ordersByDistributor[distributor.id] || [];
        const createdByDemoSum = orders
            .filter(function (o) { return o.isDemoCreated; })
            .reduce(function (sum, o) { return sum + o.total; }, 0);

        return distributor.monthlyExecutedBase + createdByDemoSum;
    }

    function creditMetrics(orderImpact) {
        const distributor = currentDistributor();
        const currentPortfolio = distributor.portfolioBalance;
        const creditLimit = distributor.creditLimit;
        const available = creditLimit - currentPortfolio;
        const afterPortfolio = currentPortfolio + orderImpact;
        const afterAvailable = creditLimit - afterPortfolio;

        return {
            currentPortfolio: currentPortfolio,
            creditLimit: creditLimit,
            available: available,
            afterPortfolio: afterPortfolio,
            afterAvailable: afterAvailable,
            usedPercent: Math.min(100, Math.max(0, (currentPortfolio / creditLimit) * 100))
        };
    }

    function makeKpiCard(title, value, extraHtml) {
        return [
            '<article class="card">',
            '<div class="kpi-title">' + title + "</div>",
            '<div class="kpi-value">' + value + "</div>",
            extraHtml || "",
            "</article>"
        ].join("");
    }

    function makeKpiCardAccent(title, value, extraHtml, accentClass) {
        return [
            '<article class="card kpi-accent ' + (accentClass || "") + '">',
            '<div class="kpi-title">' + title + "</div>",
            '<div class="kpi-value">' + value + "</div>",
            extraHtml || "",
            "</article>"
        ].join("");
    }

    function ensureChart(chartKey, canvasId, config) {
        if (state.charts[chartKey]) {
            state.charts[chartKey].destroy();
            state.charts[chartKey] = null;
        }

        const canvas = document.getElementById(canvasId);
        if (!canvas) {
            return;
        }

        state.charts[chartKey] = new Chart(canvas, config);
    }

    function renderInicio() {
        const distributor = currentDistributor();
        const executed = monthlyExecuted();
        const goal = distributor.monthlyBudgetGoal;
        const progress = Math.min(100, (executed / goal) * 100);
        const credit = creditMetrics(0);
        const orders = currentOrders();
        const hasMora = distributor.overdueBalance > 0;
        const cupoRisk = credit.usedPercent > 85;
        const carteraResume = carteraWeeklyMonthly(distributor);

        const recentHtml = orders.length === 0
            ? '<p class="muted" style="padding:0.4rem 0">No hay pedidos recientes.</p>'
            : orders.slice(0, 5).map(function (order) {
                return [
                    '<li class="recent-item">',
                    '<div class="recent-item-left">',
                    '<span class="recent-order-num">' + order.number + "</span>",
                    '<span class="recent-order-date">' + new Date(order.createdAt).toLocaleDateString("es-CO") + "</span>",
                    "</div>",
                    '<div class="recent-item-right">',
                    '<span class="recent-order-total">' + money(order.total) + "</span>",
                    statusBadge(order.status),
                    "</div>",
                    "</li>"
                ].join("");
            }).join("");

        document.getElementById("view-inicio").innerHTML = [
            '<div class="page-hero">',
            '<div class="page-hero-content">',
            '<div class="page-hero-greeting">Bienvenido,</div>',
            '<div class="page-hero-name">' + distributor.name + "</div>",
            '<div class="page-hero-meta">' + distributor.city + " · " + distributor.zone + " · Lista <strong>" + distributor.priceList + "</strong></div>",
            "</div>",
            '<div class="page-hero-month">' + (distributor.conditions ? distributor.conditions.monthLabel : "") + "</div>",
            "</div>",
            '<section class="grid-cards" style="margin-top:1rem;">',
            makeKpiCardAccent("Presupuesto", money(executed),
                '<div class="kpi-progress"><div class="kpi-progress-fill" style="width:' + progress + '%"></div></div>' +
                '<div class="muted">Meta ' + money(goal) + " · " + percent(progress) + "</div>",
                progress >= 100 ? "accent-green" : progress >= 60 ? "accent-blue" : "accent-orange"
            ),
            makeKpiCardAccent("Cupo disponible", money(credit.available),
                '<div class="muted">Asignado ' + money(credit.creditLimit) + "</div>" +
                '<div class="kpi-progress"><div class="kpi-progress-fill" style="width:' + Math.min(100, credit.usedPercent) + '%"></div></div>',
                cupoRisk ? "accent-red" : "accent-green"
            ),
            makeKpiCardAccent("Cartera", money(distributor.portfolioBalance),
                '<div class="muted ' + (hasMora ? "mora-alert" : "") + '">' + (hasMora ? "Mora " + money(distributor.overdueBalance) : "Sin mora pendiente") + "</div>",
                hasMora ? "accent-red" : "accent-blue"
            ),
            makeKpiCardAccent("Facturado esta semana", money(carteraResume.weekly),
                '<div class="muted">' + carteraResume.weekCount + ' pedido' + (carteraResume.weekCount !== 1 ? 's' : '') + ' desde el lunes</div>',
                "accent-blue"
            ),
            makeKpiCardAccent("Facturado este mes", money(carteraResume.monthly),
                '<div class="muted">' + carteraResume.monthCount + ' pedido' + (carteraResume.monthCount !== 1 ? 's' : '') + ' en el mes</div>',
                "accent-blue"
            ),
            makeKpiCardAccent("Servicio 72 h", percent(distributor.serviceKpi),
                '<div class="muted">Cumplimiento de la promesa de despacho</div>',
                distributor.serviceKpi >= 90 ? "accent-green" : distributor.serviceKpi >= 75 ? "accent-orange" : "accent-red"
            ),
            "</section>",
            '<section class="layout-2col" style="margin-top:0.9rem;">',
            '<article class="card chart-card">',
            "<h3>Avance del presupuesto</h3>",
            '<div class="chart-box"><canvas id="chartDashboardBudget"></canvas></div>',
            '<div class="chart-foot muted">' + money(executed) + " ejecutado de " + money(goal) + "</div>",
            "</article>",
            '<article class="card">',
            "<h3>Últimos pedidos</h3>",
            '<ul class="recent-list-v2">' + recentHtml + "</ul>",
            '<div class="card-foot-link"><a href="#/pedidos">Ver todos los pedidos →</a></div>',
            "</article>",
            "</section>"
        ].join("");

        document.querySelector(".card-foot-link a") && document.querySelector(".card-foot-link a").addEventListener("click", function (e) {
            e.preventDefault();
            window.location.hash = "#/pedidos";
        });

        ensureChart("dashboardBudget", "chartDashboardBudget", {
            type: "doughnut",
            data: {
                labels: ["Ejecutado", "Pendiente"],
                datasets: [{
                    data: [executed, Math.max(1, goal - executed)],
                    backgroundColor: ["#125ca3", "#e8f1fb"],
                    borderWidth: 0
                }]
            },
            options: {
                maintainAspectRatio: false,
                cutout: "70%",
                plugins: {
                    legend: { position: "bottom" },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) { return " " + money(ctx.parsed); }
                        }
                    }
                }
            }
        });
    }

    function buildCatalogProductsHtml(distributor) {
        const normalizedSearch = normalizeText(state.catalogSearch);
        const filtered = state.data.products.filter(function (product) {
            const searchable = [product.name, product.sku, product.line, product.content].join(" ");
            const textOk = normalizeText(searchable).includes(normalizedSearch);
            const lineOk = state.catalogLine === "Todas" || product.line === state.catalogLine;
            return textOk && lineOk;
        });

        if (!filtered.length) {
            return '<article class="card"><p class="muted">No hay productos para esa búsqueda o filtro.</p></article>';
        }

        return filtered.map(function (product) {
            const productPrice = getProductPriceForDistributor(product, distributor);
            const imageHtml = product.image
                ? '<div class="product-img-wrap"><img class="product-img" src="' + product.image + '" alt="' + product.name + '" loading="lazy"></div>'
                : '<div class="product-thumb"><span class="product-thumb-line">' + product.line + '</span><span class="product-thumb-sku">' + product.sku + "</span></div>";
            return [
                '<article class="product-card">',
                imageHtml,
                '<div class="product-meta">',
                '<div class="product-meta-tags"><span class="product-tag">' + product.line + "</span></div>",
                "<h4>" + product.name + "</h4>",
                "<p>" + product.content + "</p>",
                '<div class="price">Precio ' + money(productPrice) + "</div>",
                "</div>",
                '<div class="qty-add-row">',
                '<input class="qty-input" type="number" min="1" value="1" data-qty-for="' + product.sku + '">',
                '<button class="btn btn-primary btn-add-compact" type="button" data-add-sku="' + product.sku + '">+</button>',
                "</div>",
                "</article>"
            ].join("");
        }).join("");
    }

    function updateCatalogGrid() {
        const view = document.getElementById("view-catalogo");
        if (!view) {
            return;
        }

        const distributor = currentDistributor();
        const lines = ["Todas"].concat(state.data.lines);

        const grid = view.querySelector(".product-grid");
        if (grid) {
            grid.innerHTML = buildCatalogProductsHtml(distributor);
            bindCatalogAddButtons(view);
        }

        const chipsContainer = view.querySelector("#lineChips");
        if (chipsContainer) {
            chipsContainer.innerHTML = lines.map(function (line) {
                return '<button class="chip ' + (line === state.catalogLine ? "active" : "") + '" type="button" data-line="' + line + '">' + line + "</button>";
            }).join("");
        }
    }

    function bindCatalogAddButtons(view) {
        view.querySelectorAll("[data-add-sku]").forEach(function (button) {
            button.addEventListener("click", function () {
                const sku = button.getAttribute("data-add-sku");
                const qtyInput = view.querySelector('[data-qty-for="' + sku + '"]');
                const quantity = Math.max(1, Number(qtyInput.value || 1));
                addToCart(sku, quantity);
            });
        });
    }

    function renderCatalogo() {
        const view = document.getElementById("view-catalogo");
        const distributor = currentDistributor();
        const lines = ["Todas"].concat(state.data.lines);

        const chips = lines.map(function (line) {
            return '<button class="chip ' + (line === state.catalogLine ? "active" : "") + '" type="button" data-line="' + line + '">' + line + "</button>";
        }).join("");

        view.innerHTML = [
            '<section class="page-head"><h2>Catálogo</h2><p>Precio distribuidor. Sin restricción por existencias.</p></section>',
            '<section class="catalog-toolbar">',
            '<input id="catalogSearch" class="search-input" type="search" placeholder="Buscar por nombre, SKU o línea" value="' + state.catalogSearch + '">',
            '<div class="chips" id="lineChips">' + chips + "</div>",
            "</section>",
            '<section class="product-grid">' + buildCatalogProductsHtml(distributor) + "</section>"
        ].join("");

        const searchInput = document.getElementById("catalogSearch");
        searchInput.addEventListener("input", function (event) {
            state.catalogSearch = event.target.value;
            updateCatalogGrid();
        });

        view.querySelector("#lineChips").addEventListener("click", function (event) {
            const line = event.target.getAttribute("data-line");
            if (!line) {
                return;
            }
            state.catalogLine = line;
            updateCatalogGrid();
        });

        bindCatalogAddButtons(view);
    }

    function addToCart(sku, quantity) {
        const product = state.data.products.find(function (p) { return p.sku === sku; });
        const distributor = currentDistributor();
        const cart = clone(currentCart());
        const existing = cart.find(function (line) { return line.sku === sku; });
        const productPrice = getProductPriceForDistributor(product, distributor);

        if (existing) {
            existing.quantity += quantity;
            existing.price = productPrice;
            existing.subtotal = existing.quantity * productPrice;
        } else {
            cart.push({
                sku: product.sku,
                name: product.name,
                line: product.line,
                price: productPrice,
                quantity: quantity,
                subtotal: productPrice * quantity
            });
        }

        setCurrentCart(cart);
        updateCartCounter();
        showToast("Producto agregado al carrito.");
    }

    function statusBadge(status) {
        const normalizedStatus = normalizeOrderStatus(status);
        if (normalizedStatus === "Registrado") {
            return '<span class="badge registrado">Registrado</span>';
        }
        if (normalizedStatus === "Recepcionado Siesa") {
            return '<span class="badge enviado">Recepcionado Siesa</span>';
        }
        if (normalizedStatus === "En alistamiento") {
            return '<span class="badge remisionado">En alistamiento</span>';
        }
        return '<span class="badge facturado">Facturado y despachado</span>';
    }

    function cartTotal() {
        return currentCart().reduce(function (sum, item) {
            return sum + item.subtotal;
        }, 0);
    }

    function getProductBySku(sku) {
        return state.data.products.find(function (product) {
            return product.sku === sku;
        });
    }

    function cartItemImage(item) {
        const product = getProductBySku(item.sku);
        return product && product.image ? product.image : "https://placehold.co/96x96/eaf2fc/1d3d6e?text=EB";
    }

    function buildCartItemHtml(item) {
        return [
            '<article class="cart-row" data-cart-sku="' + item.sku + '">',
            '<div class="cart-row-main">',
            '<div class="cart-thumb-wrap"><img class="cart-thumb" src="' + cartItemImage(item) + '" alt="' + item.name + '"></div>',
            '<div class="cart-row-meta">',
            '<strong class="cart-row-name">' + item.name + "</strong>",
            '<div class="cart-row-sub">' + item.line + " · " + money(item.price) + " c/u</div>",
            "</div>",
            "</div>",
            '<div class="cart-row-actions">',
            '<div class="qty-step">',
            '<button class="mini-btn" data-step="-1" type="button">-</button>',
            '<span class="qty-value">' + item.quantity + "</span>",
            '<button class="mini-btn" data-step="1" type="button">+</button>',
            "</div>",
            '<div class="cart-row-subtotal">' + money(item.subtotal) + "</div>",
            '<button class="btn btn-ghost cart-remove-btn" data-remove="' + item.sku + '" type="button">Quitar</button>',
            "</div>",
            "</article>"
        ].join("");
    }

    function renderCarrito() {
        const distributor = currentDistributor();
        syncCartPricesByDistributor(distributor);
        const cart = currentCart();
        const total = cartTotal();
        const units = cart.reduce(function (sum, item) { return sum + item.quantity; }, 0);
        const discountValue = total * (distributor.conditions.financialDiscountPercent / 100);
        const credit = creditMetrics(total);

        const listHtml = cart.length === 0
            ? '<p class="muted">Tu carrito está vacío. Ve al catálogo para agregar productos.</p>'
            : cart.map(function (item) {
                return buildCartItemHtml(item);
            }).join("");

        const alerts = [];
        if (distributor.overdueBalance > 0) {
            alerts.push('<div class="alert alert-error">Tienes una mora de ' + money(distributor.overdueBalance) + ' pendiente. Esta alerta es preventiva y no bloquea el pedido.</div>');
        }
        if (credit.afterPortfolio > credit.creditLimit) {
            alerts.push('<div class="alert alert-warning">Este pedido supera tu cupo disponible en ' + money(Math.abs(credit.afterAvailable)) + '. Puedes confirmar y quedará sujeto a validación administrativa.</div>');
        }

        document.getElementById("view-carrito").innerHTML = [
            '<section class="page-head"><h2>Carrito valorizado</h2><p>Revisa en tiempo real el impacto financiero de tu pedido y confirma cuando esté listo.</p></section>',
            '<section class="cart-layout">',
            '<article class="card financial-panel">',
            "<h3>Impacto financiero</h3>",
            alerts.join(""),
            '<div class="kpi"><span>Valor de este pedido</span><strong>' + money(total) + "</strong></div>",
            '<div class="impact-compare"><div><span class="muted">Cartera actual</span><strong>' + money(credit.currentPortfolio) + '</strong></div><div><span class="muted">Cartera después</span><strong>' + money(credit.afterPortfolio) + '</strong></div></div>',
            '<div class="impact-compare"><div><span class="muted">Cupo disponible</span><strong>' + money(credit.available) + '</strong></div><div><span class="muted">Cupo restante</span><strong>' + money(credit.afterAvailable) + '</strong></div></div>',
            '<div class="kpi"><span>Descuento financiero referencial</span><strong>' + distributor.conditions.financialDiscountPercent + "% · ahorro " + money(discountValue) + "</strong></div>",
            '<div class="kpi"><span>Política de despacho</span><strong>Hasta ' + distributor.conditions.dispatchHours + " horas</strong></div>",
            "</article>",
            '<article class="card cart-items-card">',
            "<h3>Detalle del pedido</h3>",
            '<div class="cart-items-list">' + listHtml + '</div>',
            '<div class="total-strip"><span>Total unidades</span><span>' + units + "</span></div>",
            '<div class="total-strip total-strip-strong"><span>Total del pedido</span><span>' + money(total) + "</span></div>",
            '<button id="confirmOrderBtn" class="btn btn-primary btn-block" ' + (cart.length === 0 ? "disabled" : "") + ' type="button">Confirmar pedido</button>',
            "</article>",
            "</section>"
        ].join("");

        document.querySelectorAll("[data-cart-sku]").forEach(function (row) {
            row.addEventListener("click", function (event) {
                const sku = row.getAttribute("data-cart-sku");
                if (event.target.matches("[data-step]")) {
                    const step = Number(event.target.getAttribute("data-step"));
                    updateCartQty(sku, step);
                }
            });
        });

        document.querySelectorAll("[data-remove]").forEach(function (button) {
            button.addEventListener("click", function (event) {
                event.stopPropagation();
                removeFromCart(button.getAttribute("data-remove"));
            });
        });

        const confirmButton = document.getElementById("confirmOrderBtn");
        if (confirmButton) {
            confirmButton.addEventListener("click", confirmOrder);
        }
    }

    function updateCartQty(sku, step) {
        const cart = clone(currentCart());
        const item = cart.find(function (line) { return line.sku === sku; });
        if (!item) {
            return;
        }

        item.quantity = Math.max(1, item.quantity + step);
        item.subtotal = item.quantity * item.price;
        setCurrentCart(cart);
        updateCartCounter();
        renderCarrito();
    }

    function removeFromCart(sku) {
        const cart = clone(currentCart()).filter(function (line) {
            return line.sku !== sku;
        });
        setCurrentCart(cart);
        updateCartCounter();
        renderCarrito();
    }

    function nextOrderNumber() {
        const distributor = currentDistributor();
        const current = state.data.orderSequence[distributor.id] || 1000;
        const next = current + 1;
        state.data.orderSequence[distributor.id] = next;
        return "PED-" + String(next).padStart(4, "0");
    }

    function confirmOrder() {
        const distributor = currentDistributor();
        const cart = clone(currentCart());
        if (!cart.length) {
            return;
        }

        const now = new Date().toISOString();
        const number = nextOrderNumber();
        const total = cart.reduce(function (sum, line) { return sum + line.subtotal; }, 0);

        const order = {
            id: number,
            number: number,
            createdAt: now,
            status: "Registrado",
            lines: cart,
            total: total,
            isDemoCreated: true,
            dispatchType: null,
            adminObservation: "",
            timeline: [{ status: "Registrado", at: now }]
        };

        state.data.ordersByDistributor[distributor.id].push(order);
        state.data.cartByDistributor[distributor.id] = [];
        writeStorage(state.data);
        updateCartCounter();
        showToast("Pedido " + number + " creado en estado Registrado.");
        window.location.hash = "#/pedidos";
    }

    function slaStatus(order) {
        const startAt = slaCountStart(order.createdAt);
        const referenceDate = orderSlaReferenceDate(order);
        const elapsed = Math.max(0, Math.floor((referenceDate.getTime() - startAt.getTime()) / 3600000));
        const finalStatus = isFinalStatus(order.status);

        if (finalStatus && elapsed <= 72) {
            return { text: "A tiempo", className: "sla-ok" };
        }

        if (finalStatus && elapsed > 72) {
            return { text: "Fuera de SLA", className: "sla-error" };
        }

        if (!finalStatus && elapsed < 72) {
            return { text: "En término - quedan " + (72 - elapsed) + "h", className: "sla-risk" };
        }

        return { text: "Fuera de SLA", className: "sla-error" };
    }

    function renderPedidos() {
        const orders = currentOrders();
        const totalCount = orders.length;
        const enProceso = orders.filter(function (o) { return !isFinalStatus(o.status); }).length;
        const facturados = orders.filter(function (o) { return isFinalStatus(o.status); }).length;

        const rows = orders.length === 0
            ? '<tr><td colspan="5" style="text-align:center;padding:1.5rem;color:var(--eb-muted)">No hay pedidos registrados aún.</td></tr>'
            : orders.map(function (order) {
                const sla = slaStatus(order);
                return [
                    '<tr class="order-row-clickable" data-order-id="' + order.id + '">',
                    '<td><strong>' + order.number + "</strong></td>",
                    "<td>" + new Date(order.createdAt).toLocaleDateString("es-CO") + "</td>",
                    "<td><strong>" + money(order.total) + "</strong></td>",
                    "<td>" + statusBadge(order.status) + "</td>",
                    '<td><span class="' + sla.className + '">' + sla.text + "</span></td>",
                    "</tr>"
                ].join("");
            }).join("");

        document.getElementById("view-pedidos").innerHTML = [
            '<section class="page-head"><h2>Mis pedidos</h2><p>Haz clic en un pedido para ver el detalle y avanzar su estado en modo demo.</p></section>',
            '<section class="report-actions">',
            '<button class="btn btn-soft btn-xs" type="button" id="downloadPedidosExcelBtn">Descargar Excel</button>',
            '<button class="btn btn-soft btn-xs" type="button" id="downloadPedidosPdfBtn">Descargar PDF</button>',
            '</section>',
            '<section class="pedidos-summary">',
            '<div class="pedidos-stat"><span class="pedidos-stat-num">' + totalCount + "</span><span class='pedidos-stat-label'>Total</span></div>",
            '<div class="pedidos-stat"><span class="pedidos-stat-num" style="color:#e8a33d">' + enProceso + "</span><span class='pedidos-stat-label'>En proceso</span></div>",
            '<div class="pedidos-stat"><span class="pedidos-stat-num" style="color:#1e9e6a">' + facturados + "</span><span class='pedidos-stat-label'>Despachados</span></div>",
            "</section>",
            '<article class="card" style="margin-top:0.75rem;">',
            '<table class="orders-table">',
            "<thead><tr><th>Pedido</th><th>Fecha</th><th>Valor</th><th>Estado</th><th>SLA 72 h</th></tr></thead>",
            "<tbody>" + rows + "</tbody>",
            "</table>",
            "</article>"
        ].join("");

        document.querySelectorAll("[data-order-id]").forEach(function (row) {
            row.addEventListener("click", function () {
                openOrderDrawer(row.getAttribute("data-order-id"));
            });
        });

        const downloadExcelBtn = document.getElementById("downloadPedidosExcelBtn");
        if (downloadExcelBtn) {
            downloadExcelBtn.addEventListener("click", function () {
                downloadOrdersReport("excel", orders, currentDistributor().name);
            });
        }

        const downloadPdfBtn = document.getElementById("downloadPedidosPdfBtn");
        if (downloadPdfBtn) {
            downloadPdfBtn.addEventListener("click", function () {
                downloadOrdersReport("pdf", orders, currentDistributor().name);
            });
        }
    }

    function openOrderDrawer(orderId) {
        const distributor = currentDistributor();
        const order = (state.data.ordersByDistributor[distributor.id] || []).find(function (o) {
            return o.id === orderId;
        });

        if (!order) {
            return;
        }

        const currentStatusIndex = window.DEMO_SEED.statusFlow.indexOf(order.status);
        const timeline = window.DEMO_SEED.statusFlow.map(function (status, index) {
            const reached = index <= currentStatusIndex;
            return [
                '<div class="timeline-item ' + (status === order.status ? "current" : "") + '">',
                '<div class="timeline-item-main"><span class="timeline-dot ' + (reached ? "reached" : "") + '"></span><strong>' + status + "</strong></div>",
                '<span class="timeline-state ' + (reached ? "ok" : "pending") + '">' + (reached ? "OK" : "Pendiente") + "</span>",
                "</div>"
            ].join("");
        }).join("");

        const lines = order.lines.map(function (line) {
            return [
                '<li class="order-line-card">',
                '<div class="order-line-main">',
                '<div class="order-line-thumb-wrap"><img class="order-line-thumb" src="' + cartItemImage(line) + '" alt="' + line.name + '"></div>',
                '<div class="order-line-info">',
                '<strong class="order-line-name">' + line.name + "</strong>",
                '<div class="order-line-meta">' + line.line + " · " + money(line.price) + " c/u</div>",
                "</div>",
                "</div>",
                '<div class="order-line-resume">',
                '<span class="order-line-qty">x' + line.quantity + "</span>",
                '<strong class="order-line-total">' + money(line.subtotal) + "</strong>",
                "</div>",
                "</li>"
            ].join("");
        }).join("");

        const dispatchDetail = isFinalStatus(order.status)
            ? '<p class="muted" style="margin-top:0.35rem;">Despacho: <strong>' + (order.dispatchType === "parcial" ? "Parcial" : "Completo") + "</strong></p>"
            : "";
        const observationHtml = order.adminObservation
            ? '<article class="card" style="margin-top:0.65rem;"><h4 style="margin-top:0;">Observaciones administrativas</h4><p class="muted" style="margin-bottom:0;">' + order.adminObservation + "</p></article>"
            : "";

        el.drawerBody.innerHTML = [
            '<div class="order-detail-head">',
            '<div><p><strong>' + order.number + '</strong></p><p class="muted">Ingreso: ' + new Date(order.createdAt).toLocaleString("es-CO") + "</p></div>",
            '<div class="order-detail-status">Estado actual: ' + statusBadge(order.status) + "</div>",
            "</div>",
            dispatchDetail,
            "<h4>Líneas</h4>",
            '<ul class="order-lines-list">' + lines + "</ul>",
            "<p><strong>Total: " + money(order.total) + "</strong></p>",
            "<h4>Línea de tiempo</h4>",
            '<div class="timeline">' + timeline + "</div>",
            observationHtml,
            order.isDemoCreated && !isFinalStatus(order.status)
                ? '<button id="advanceStatusBtn" class="btn btn-soft" type="button">Avanzar estado (modo demo)</button>'
                : ""
        ].join("");

        if (order.isDemoCreated && !isFinalStatus(order.status)) {
            const button = document.getElementById("advanceStatusBtn");
            button.addEventListener("click", function () {
                advanceOrderStatus(order.id);
            });
        }

        el.drawer.classList.add("open");
        el.drawer.setAttribute("aria-hidden", "false");
    }

    function closeDrawer() {
        el.drawer.classList.remove("open");
        el.drawer.setAttribute("aria-hidden", "true");
    }

    function advanceOrderStatus(orderId) {
        const distributor = currentDistributor();
        const orders = state.data.ordersByDistributor[distributor.id] || [];
        const order = orders.find(function (o) { return o.id === orderId; });
        if (!order) {
            return;
        }

        const flow = window.DEMO_SEED.statusFlow;
        const currentIndex = flow.indexOf(order.status);
        if (currentIndex === -1 || currentIndex === flow.length - 1) {
            return;
        }

        const nextStatus = flow[currentIndex + 1];
        order.status = nextStatus;
        if (isFinalStatus(nextStatus) && !order.dispatchType) {
            order.dispatchType = "completo";
        }
        order.timeline = order.timeline || [];
        order.timeline.push({ status: nextStatus, at: new Date().toISOString() });
        writeStorage(state.data);

        renderPedidos();
        openOrderDrawer(orderId);
        showToast("Estado actualizado a " + nextStatus + ".");
    }

    function categoryExecutionDataForDistributor(distributor) {
        const map = clone(distributor.categoryExecutionBase);

        ordersForDistributor(distributor.id).filter(function (order) {
            return order.isDemoCreated;
        }).forEach(function (order) {
            order.lines.forEach(function (line) {
                map[line.line] = (map[line.line] || 0) + line.subtotal;
            });
        });

        return map;
    }

    function categoryExecutionData() {
        const distributor = currentDistributor();
        return categoryExecutionDataForDistributor(distributor);
    }

    function renderPresupuesto() {
        const distributor = currentDistributor();
        const executed = monthlyExecuted();
        const goal = distributor.monthlyBudgetGoal;
        const pending = Math.max(0, goal - executed);
        const progress = (executed / goal) * 100;
        const categories = categoryExecutionData();

        const categoryLabels = Object.keys(categories);
        const categoryValues = categoryLabels.map(function (key) { return categories[key]; });

        const progressMsg = progress >= 100
            ? '<div class="budget-banner banner-green">Meta cumplida este mes. Excelente resultado.</div>'
            : progress >= 75
                ? '<div class="budget-banner banner-blue">Buen ritmo. Quedan ' + money(pending) + ' para completar la meta.</div>'
                : '<div class="budget-banner banner-blue">Avance actual: ' + percent(progress) + ' de la meta mensual.</div>';

        document.getElementById("view-presupuesto").innerHTML = [
            '<section class="page-head"><h2>Presupuesto</h2><p>' + (distributor.conditions ? distributor.conditions.monthLabel : "Mes vigente") + ' · actualizable mensualmente por el administrador.</p></section>',
            progressMsg,
            '<section class="grid-cards" style="margin-top:0.75rem;">',
            makeKpiCardAccent("Meta del mes", money(goal), '<div class="muted">Objetivo comercial asignado</div>', "accent-blue"),
            makeKpiCardAccent("Ejecutado", money(executed),
                '<div class="kpi-progress"><div class="kpi-progress-fill" style="width:' + Math.min(100, progress) + '%"></div></div>',
                progress >= 100 ? "accent-green" : "accent-blue"
            ),
            makeKpiCardAccent("Faltante", money(pending), '<div class="muted">Para completar la meta del mes</div>', pending === 0 ? "accent-green" : "accent-orange"),
            makeKpiCardAccent("Avance", percent(progress), '<div class="muted">Del objetivo mensual</div>', progress >= 100 ? "accent-green" : progress >= 60 ? "accent-blue" : "accent-orange"),
            "</section>",
            '<section class="layout-2col" style="margin-top: 0.9rem;">',
            '<article class="card chart-card"><h3>Meta vs ejecutado</h3><div class="chart-box"><canvas id="chartBudgetMain"></canvas></div></article>',
            '<article class="card chart-card"><h3>Ejecución por línea</h3><div class="chart-box"><canvas id="chartBudgetLine"></canvas></div></article>',
            "</section>"
        ].join("");

        ensureChart("budgetMain", "chartBudgetMain", {
            type: "bar",
            data: {
                labels: ["Meta", "Ejecutado"],
                datasets: [{
                    label: "COP",
                    data: [goal, executed],
                    backgroundColor: ["#f3bf74", "#125ca3"]
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        ticks: {
                            callback: function (value) {
                                return money(value);
                            }
                        }
                    }
                }
            }
        });

        ensureChart("budgetByLine", "chartBudgetLine", {
            type: "bar",
            data: {
                labels: categoryLabels,
                datasets: [{
                    label: "Ejecución por línea",
                    data: categoryValues,
                    backgroundColor: ["#125ca3", "#1e9e6a", "#506ba1", "#e8a33d", "#4d7cad", "#88a8ce", "#0e4f91"]
                }]
            },
            options: {
                maintainAspectRatio: false,
                indexAxis: "y",
                plugins: { legend: { display: false } },
                scales: {
                    x: {
                        ticks: {
                            callback: function (value) {
                                return money(value);
                            }
                        }
                    }
                }
            }
        });
    }

    function renderCondiciones() {
        const distributor = currentDistributor();
        const conditions = distributor.conditions;
        const hasMora = distributor.overdueBalance > 0;

        function condCard(label, value, sub, highlight) {
            return [
                '<article class="cond-card' + (highlight ? " cond-card-highlight" : "") + '">',
                '<div class="cond-card-body">',
                '<div class="cond-card-label">' + label + "</div>",
                '<div class="cond-card-value">' + value + "</div>",
                sub ? '<div class="cond-card-sub">' + sub + "</div>" : "",
                "</div>",
                "</article>"
            ].join("");
        }

        document.getElementById("view-condiciones").innerHTML = [
            '<section class="page-head"><h2>Condiciones comerciales</h2><p>Vigentes para <strong>' + conditions.monthLabel + "</strong> · actualizables cada mes por el administrador.</p></section>",
            '<section class="cond-cards-grid">',
            condCard("Lista de precios", distributor.priceList, "Tabla de precios asignada a este distribuidor", true),
            condCard("Descuento financiero", conditions.financialDiscountPercent + "% pronto pago", "Aplica en pagos hasta " + conditions.earlyPaymentDays + " días", true),
            condCard("Plazo de crédito", conditions.creditDays + " días", "Tiempo máximo para pago de facturas"),
            condCard("Política de despacho", "Hasta " + conditions.dispatchHours + " h", "Desde el ingreso del pedido al sistema"),
            condCard("Cupo asignado", money(distributor.creditLimit), "Límite de cartera aprobado"),
            condCard("Cartera actual", money(distributor.portfolioBalance), hasMora ? "Mora pendiente: " + money(distributor.overdueBalance) : "Sin mora pendiente"),
            "</section>"
        ].join("");
    }

    function renderAdministrador() {
        const adminConfig = getAdminConfig();
        const totalOrders = state.data.distributors.reduce(function (acc, distributor) {
            return acc + (state.data.ordersByDistributor[distributor.id] || []).length;
        }, 0);
        const atRisk = state.data.distributors.filter(function (d) {
            return d.overdueBalance > 0 || d.portfolioBalance > d.creditLimit * 0.85;
        }).length;
        const totalExecuted = state.data.distributors.reduce(function (acc, distributor) {
            const created = (state.data.ordersByDistributor[distributor.id] || [])
                .filter(function (order) { return order.isDemoCreated; })
                .reduce(function (sum, order) { return sum + order.total; }, 0);
            return acc + distributor.monthlyExecutedBase + created;
        }, 0);
        const rows = state.data.distributors.map(function (d) {
            const used = Math.round((d.portfolioBalance / d.creditLimit) * 100);
            const executed = monthlyExecutedForDistributor(d);
            const budgetProgress = d.monthlyBudgetGoal > 0
                ? Math.round((executed / d.monthlyBudgetGoal) * 100)
                : 0;
            return [
                "<tr data-admin-distributor=\"" + d.id + "\" class=\"" + (d.isActive ? "" : "row-inactive") + "\">",
                "<td><strong>" + d.name + "</strong><div class=\"muted\">" + d.city + " / " + d.zone + "</div></td>",
                "<td>" + d.priceList + "</td>",
                "<td>" + money(d.monthlyBudgetGoal) + "</td>",
                "<td>" + money(executed) + "</td>",
                "<td>" + budgetProgress + "%</td>",
                "<td>" + money(d.creditLimit) + "</td>",
                "<td>" + money(d.portfolioBalance) + "</td>",
                "<td>" + money(d.overdueBalance) + "</td>",
                "<td>" + used + "%</td>",
                "<td><span class=\"status-pill " + (d.isActive ? "status-active" : "status-inactive") + "\">" + (d.isActive ? "Activo" : "Inactivo") + "</span></td>",
                "<td><div class=\"admin-actions\"><button type=\"button\" class=\"btn btn-soft btn-xs\" data-edit-distributor=\"" + d.id + "\">Editar</button><button type=\"button\" class=\"btn btn-soft btn-xs\" data-toggle-active=\"" + d.id + "\">" + (d.isActive ? "Desactivar" : "Activar") + "</button></div></td>",
                "</tr>"
            ].join("");
        }).join("");

        document.getElementById("view-administrador").innerHTML = [
            '<section class="page-head"><h2>Administración - Distribuidores</h2><p>Gestiona distribuidores, su cupo y estado operativo del portal.</p></section>',
            '<section class="grid-cards">',
            makeKpiCard("Distribuidores demo", String(state.data.distributors.length)),
            makeKpiCard("Pedidos en el portal", String(totalOrders)),
            makeKpiCard("Carteras en riesgo", String(atRisk)),
            makeKpiCard("Ejecución total mes", money(totalExecuted)),
            '</section>',
            '<section style="margin-top:0.9rem;">',
            '<article class="card">',
            '<div class="section-head-inline"><h3>Listado de distribuidores</h3><button type="button" class="btn btn-primary btn-xs" id="adminOpenCreateBtn">+ Crear distribuidor</button></div>',
            '<table class="orders-table"><thead><tr><th>Distribuidor</th><th>Lista</th><th>Presupuesto</th><th>Ejecutado</th><th>% Cumpl.</th><th>Cupo</th><th>Cartera</th><th>Mora</th><th>Uso cupo</th><th>Estado</th><th>Acción</th></tr></thead><tbody>' + rows + '</tbody></table>',
            '</article>',
            '</section>',
            '<section class="admin-tip" style="margin-top:0.75rem;">La vista de <strong>Análisis</strong> está disponible como módulo separado en el menú.</section>'
        ].join("");

        document.querySelectorAll("[data-admin-distributor]").forEach(function (row) {
            row.addEventListener("click", function () {
                adminConfig.selectedDistributorId = row.getAttribute("data-admin-distributor");
                writeStorage(state.data);
            });
        });

        document.querySelectorAll("[data-edit-distributor]").forEach(function (button) {
            button.addEventListener("click", function (event) {
                event.stopPropagation();
                openAdminDistributorEditor(button.getAttribute("data-edit-distributor"));
            });
        });

        document.querySelectorAll("[data-toggle-active]").forEach(function (button) {
            button.addEventListener("click", function (event) {
                event.stopPropagation();
                const distributorId = button.getAttribute("data-toggle-active");
                const distributor = state.data.distributors.find(function (item) { return item.id === distributorId; });

                if (!distributor) {
                    return;
                }

                distributor.isActive = !distributor.isActive;
                writeStorage(state.data);
                showToast("Estado actualizado: " + (distributor.isActive ? "activo" : "inactivo") + ".");
                renderAdministrador();
            });
        });

        const openCreateButton = document.getElementById("adminOpenCreateBtn");
        if (openCreateButton) {
            openCreateButton.addEventListener("click", function () {
                openAdminCreateDistributorModal();
            });
        }
    }

    function renderAdminAnalisis() {
        const adminConfig = getAdminConfig();
        const selectedDistributor = getDistributorById(adminConfig.selectedDistributorId) || state.data.distributors[0];
        const selectedOrders = selectedDistributor ? ordersForDistributor(selectedDistributor.id) : [];
        const selectedExecuted = selectedDistributor ? monthlyExecutedForDistributor(selectedDistributor) : 0;
        const selectedGoal = selectedDistributor ? selectedDistributor.monthlyBudgetGoal : 0;
        const selectedProgress = selectedGoal > 0 ? Math.round((selectedExecuted / selectedGoal) * 100) : 0;
        const selectedCartera = selectedDistributor ? carteraWeeklyMonthly(selectedDistributor) : { weekly: 0, monthly: 0, weekCount: 0, monthCount: 0 };
        const distributorOptions = state.data.distributors.map(function (d) {
            const selected = selectedDistributor && d.id === selectedDistributor.id ? "selected" : "";
            return '<option value="' + d.id + '" ' + selected + '>' + d.name + "</option>";
        }).join("");
        const adminOrderRows = selectedOrders.length
            ? selectedOrders.map(function (order) {
                return [
                    "<tr>",
                    "<td><strong>" + order.number + "</strong></td>",
                    "<td>" + new Date(order.createdAt).toLocaleDateString("es-CO") + "</td>",
                    "<td>" + money(order.total) + "</td>",
                    "<td>" + statusBadge(order.status) + "</td>",
                    "<td>" + (isFinalStatus(order.status) ? (order.dispatchType === "parcial" ? "Parcial" : "Completo") : "-") + "</td>",
                    "<td>" + (order.adminObservation ? order.adminObservation : '<span class=\"muted\">Sin observación</span>') + "</td>",
                    '<td><button type="button" class="btn btn-soft btn-xs" data-admin-edit-order="' + order.id + '">Editar</button></td>',
                    "</tr>"
                ].join("");
            }).join("")
            : '<tr><td colspan="7" style="text-align:center;padding:1rem;color:var(--eb-muted)">No hay pedidos para este distribuidor.</td></tr>';

        document.getElementById("view-admin-analisis").innerHTML = [
            '<section class="page-head"><h2>Administración - Análisis por distribuidor</h2><p>Indicadores comerciales, cartera y seguimiento de pedidos por distribuidor.</p></section>',
            '<article class="card">',
            '<div class="section-head-inline"><h3>Análisis por distribuidor</h3><select id="adminDistributorFilter">' + distributorOptions + '</select></div>',
            '<section class="grid-cards" style="margin-top:0.6rem;">',
            makeKpiCard("Presupuesto", money(selectedGoal)),
            makeKpiCard("Ejecutado", money(selectedExecuted), '<div class="muted">Cumplimiento: ' + selectedProgress + '%</div>'),
            makeKpiCard("Facturado esta semana", money(selectedCartera.weekly), '<div class="muted">' + selectedCartera.weekCount + ' pedido' + (selectedCartera.weekCount !== 1 ? 's' : '') + '</div>'),
            makeKpiCard("Facturado este mes", money(selectedCartera.monthly), '<div class="muted">' + selectedCartera.monthCount + ' pedido' + (selectedCartera.monthCount !== 1 ? 's' : '') + '</div>'),
            '</section>',
            '<section class="layout-2col" style="margin-top:0.75rem;">',
            '<article class="card chart-card"><h3>Meta vs ejecutado</h3><div class="chart-box"><canvas id="chartAdminDistributorBudget"></canvas></div></article>',
            '<article class="card chart-card"><h3>Ejecución por línea</h3><div class="chart-box"><canvas id="chartAdminDistributorLine"></canvas></div></article>',
            '</section>',
            '</article>',
            '<section style="margin-top:0.9rem;">',
            '<article class="card">',
            '<div class="section-head-inline"><h3>Pedidos del distribuidor seleccionado</h3><div class="inline-actions"><button type="button" class="btn btn-soft btn-xs" id="adminDownloadOrdersExcelBtn">Descargar Excel</button><button type="button" class="btn btn-soft btn-xs" id="adminDownloadOrdersPdfBtn">Descargar PDF</button></div></div>',
            '<table class="orders-table"><thead><tr><th>Pedido</th><th>Fecha</th><th>Valor</th><th>Estado</th><th>Despacho</th><th>Observación</th><th>Acción</th></tr></thead><tbody>' + adminOrderRows + '</tbody></table>',
            '</article>',
            '</section>'
        ].join("");

        if (selectedDistributor) {
            const selectedCategories = categoryExecutionDataForDistributor(selectedDistributor);
            const categoryLabels = Object.keys(selectedCategories);
            const categoryValues = categoryLabels.map(function (key) { return selectedCategories[key]; });

            ensureChart("adminDistributorBudget", "chartAdminDistributorBudget", {
                type: "bar",
                data: {
                    labels: ["Meta", "Ejecutado"],
                    datasets: [{
                        label: "COP",
                        data: [selectedGoal, selectedExecuted],
                        backgroundColor: ["#f3bf74", "#125ca3"]
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: {
                            ticks: {
                                callback: function (value) { return money(value); }
                            }
                        }
                    }
                }
            });

            ensureChart("adminDistributorLine", "chartAdminDistributorLine", {
                type: "bar",
                data: {
                    labels: categoryLabels,
                    datasets: [{
                        label: "Ejecución por línea",
                        data: categoryValues,
                        backgroundColor: ["#125ca3", "#1e9e6a", "#506ba1", "#e8a33d", "#4d7cad", "#88a8ce", "#0e4f91"]
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    indexAxis: "y",
                    plugins: { legend: { display: false } },
                    scales: {
                        x: {
                            ticks: {
                                callback: function (value) { return money(value); }
                            }
                        }
                    }
                }
            });
        }

        const distributorFilter = document.getElementById("adminDistributorFilter");
        if (distributorFilter) {
            distributorFilter.addEventListener("change", function () {
                adminConfig.selectedDistributorId = distributorFilter.value;
                writeStorage(state.data);
                renderAdminAnalisis();
            });
        }

        document.querySelectorAll("[data-admin-edit-order]").forEach(function (button) {
            button.addEventListener("click", function () {
                if (!selectedDistributor) {
                    return;
                }
                openAdminOrderEditor(selectedDistributor.id, button.getAttribute("data-admin-edit-order"));
            });
        });

        const adminDownloadExcelBtn = document.getElementById("adminDownloadOrdersExcelBtn");
        if (adminDownloadExcelBtn && selectedDistributor) {
            adminDownloadExcelBtn.addEventListener("click", function () {
                downloadOrdersReport("excel", selectedOrders, selectedDistributor.name + " (Admin)");
            });
        }

        const adminDownloadPdfBtn = document.getElementById("adminDownloadOrdersPdfBtn");
        if (adminDownloadPdfBtn && selectedDistributor) {
            adminDownloadPdfBtn.addEventListener("click", function () {
                downloadOrdersReport("pdf", selectedOrders, selectedDistributor.name + " (Admin)");
            });
        }
    }

    function renderAdminListas() {
        const adminConfig = getAdminConfig();
        const priceListOptions = adminConfig.priceListOptions || [];
        const selectedPriceList = adminConfig.selectedPriceList || priceListOptions[0] || "";
        const selectedListPrices = adminConfig.priceMatrixByList[selectedPriceList] || {};
        const priceListBadges = priceListOptions.map(function (item) {
            return '<span class="admin-badge">' + item + '<button type="button" class="admin-badge-remove" data-price-list-remove="' + item + '" aria-label="Eliminar opción">x</button></span>';
        }).join("");

        const listSelectorOptions = priceListOptions.map(function (item) {
            const selected = item === selectedPriceList ? "selected" : "";
            return '<option value="' + item + '" ' + selected + '>' + item + '</option>';
        }).join("");

        const productRows = state.data.products.map(function (product) {
            const listPrice = Number(selectedListPrices[product.sku]);
            const finalPrice = Number.isFinite(listPrice) ? listPrice : product.price;

            return [
                "<tr>",
                "<td>" + product.sku + "</td>",
                "<td><strong>" + product.name + "</strong><div class=\"muted\">" + product.line + "</div></td>",
                "<td>" + money(product.price) + "</td>",
                "<td><input type=\"number\" min=\"0\" step=\"100\" data-price-sku=\"" + product.sku + "\" value=\"" + finalPrice + "\"></td>",
                "</tr>"
            ].join("");
        }).join("");

        document.getElementById("view-admin-listas").innerHTML = [
            '<section class="page-head"><h2>Administración - Listas de precios</h2><p>Define el precio por producto para cada lista y afecta catálogo/carrito del distribuidor.</p></section>',
            '<article class="card">',
            '<h3>Opciones de lista de precios</h3>',
            '<div id="adminPriceListBadges" class="admin-badges">' + priceListBadges + '</div>',
            '<form id="adminPriceListForm" class="admin-form admin-form-inline">',
            '<input id="adminNewPriceList" placeholder="Nueva lista (ej. Mayorista C)" required>',
            '<button class="btn btn-soft" type="submit">Agregar opción</button>',
            '</form>',
            '</article>',
            '<article class="card" style="margin-top:0.9rem;">',
            '<div class="section-head-inline">',
            '<h3>Precios por producto</h3>',
            '<select id="adminPriceListSelector">' + listSelectorOptions + '</select>',
            '</div>',
            '<table class="orders-table">',
            '<thead><tr><th>SKU</th><th>Producto</th><th>Base</th><th>Precio en lista</th></tr></thead>',
            '<tbody>' + productRows + '</tbody>',
            '</table>',
            '<div style="margin-top:0.75rem; display:flex; justify-content:flex-end;">',
            '<button class="btn btn-primary" id="adminSaveListPricesBtn" type="button">Guardar precios de la lista</button>',
            '</div>',
            '</article>'
        ].join("");

        const selector = document.getElementById("adminPriceListSelector");
        if (selector) {
            selector.addEventListener("change", function () {
                adminConfig.selectedPriceList = selector.value;
                writeStorage(state.data);
                renderAdminListas();
            });
        }

        const savePricesButton = document.getElementById("adminSaveListPricesBtn");
        if (savePricesButton) {
            savePricesButton.addEventListener("click", function () {
                const activeList = adminConfig.selectedPriceList;
                if (!activeList) {
                    showToast("Selecciona una lista de precios.");
                    return;
                }

                if (!adminConfig.priceMatrixByList[activeList]) {
                    adminConfig.priceMatrixByList[activeList] = {};
                }

                document.querySelectorAll("[data-price-sku]").forEach(function (input) {
                    const sku = input.getAttribute("data-price-sku");
                    const value = Math.max(0, toNumber(input.value));
                    adminConfig.priceMatrixByList[activeList][sku] = value;
                });

                state.data.distributors.forEach(function (distributor) {
                    syncCartPricesByDistributor(distributor);
                });
                writeStorage(state.data);
                showToast("Precios de la lista actualizados.");
            });
        }

        document.getElementById("adminPriceListForm").addEventListener("submit", function (event) {
            event.preventDefault();
            const input = document.getElementById("adminNewPriceList");
            const newOption = input.value.trim();

            if (!newOption) {
                return;
            }

            if (adminConfig.priceListOptions.includes(newOption)) {
                showToast("Esa lista de precios ya existe.");
                return;
            }

            adminConfig.priceListOptions.push(newOption);
            adminConfig.priceMatrixByList[newOption] = {};
            state.data.products.forEach(function (product) {
                adminConfig.priceMatrixByList[newOption][product.sku] = product.price;
            });
            adminConfig.selectedPriceList = newOption;
            writeStorage(state.data);
            showToast("Opción de lista de precios agregada.");
            renderAdminListas();
        });

        document.querySelectorAll("[data-price-list-remove]").forEach(function (button) {
            button.addEventListener("click", function () {
                const option = button.getAttribute("data-price-list-remove");
                const inUse = state.data.distributors.some(function (d) { return d.priceList === option; });

                if (inUse) {
                    showToast("No puedes eliminar una lista en uso por un distribuidor.");
                    return;
                }

                adminConfig.priceListOptions = adminConfig.priceListOptions.filter(function (value) {
                    return value !== option;
                });
                delete adminConfig.priceMatrixByList[option];
                if (adminConfig.selectedPriceList === option) {
                    adminConfig.selectedPriceList = adminConfig.priceListOptions[0] || null;
                }
                writeStorage(state.data);
                showToast("Opción eliminada.");
                renderAdminListas();
            });
        });
    }

    function renderAdminProductos() {
        const productRows = state.data.products.map(function (product) {
            const isActive = product.isActive !== false;
            return [
                "<tr class=\"" + (isActive ? "" : "row-inactive") + "\">",
                '<td><div class="admin-product-thumb-wrap"><img class="admin-product-thumb" src="' + (product.image || "") + '" alt="' + product.name + '" loading="lazy"></div></td>',
                "<td><strong>" + product.name + "</strong><div class=\"muted\">" + product.sku + "</div></td>",
                "<td>" + product.line + "</td>",
                "<td>" + product.content + "</td>",
                "<td>" + money(product.price) + "</td>",
                '<td><span class="status-pill ' + (isActive ? "status-active" : "status-inactive") + '">' + (isActive ? "Activo" : "Inactivo") + "</span></td>",
                '<td><button type="button" class="btn btn-soft btn-xs" data-view-product="' + product.sku + '">Ver detalle</button></td>',
                "</tr>"
            ].join("");
        }).join("");

        document.getElementById("view-admin-productos").innerHTML = [
            '<section class="page-head"><h2>Administración - Productos</h2><p>Catálogo completo con estado y precios por lista.</p></section>',
            '<article class="card">',
            '<table class="orders-table admin-products-table">',
            "<thead><tr><th></th><th>Producto</th><th>Línea</th><th>Contenido</th><th>Precio base</th><th>Estado</th><th>Acción</th></tr></thead>",
            "<tbody>" + productRows + "</tbody>",
            "</table>",
            "</article>"
        ].join("");

        document.querySelectorAll("[data-view-product]").forEach(function (button) {
            button.addEventListener("click", function () {
                openAdminProductoModal(button.getAttribute("data-view-product"));
            });
        });
    }

    function openAdminProductoModal(sku) {
        const product = state.data.products.find(function (p) { return p.sku === sku; });
        if (!product) { return; }

        const adminConfig = getAdminConfig();
        const priceListOptions = adminConfig.priceListOptions || [];
        const isActive = product.isActive !== false;

        const priceRows = priceListOptions.map(function (listName) {
            const listPrices = adminConfig.priceMatrixByList[listName] || {};
            const price = Number(listPrices[sku]);
            const finalPrice = Number.isFinite(price) ? price : product.price;
            return "<tr><td>" + listName + "</td><td><strong>" + money(finalPrice) + "</strong></td></tr>";
        }).join("");

        const modalId = "adminProductoModal";
        const existing = document.getElementById(modalId);
        if (existing) { existing.remove(); }

        const modal = document.createElement("div");
        modal.id = modalId;
        modal.className = "admin-modal";
        modal.innerHTML = [
            '<div class="admin-modal-content admin-modal-producto">',
            '<div class="admin-modal-head">',
            '<h3>' + product.name + "</h3>",
            '<button type="button" class="btn btn-ghost btn-xs" id="adminProductoCloseBtn">Cerrar</button>',
            "</div>",
            '<div class="admin-producto-body">',
            '<div class="admin-producto-thumb-lg-wrap">',
            '<img class="admin-producto-thumb-lg" src="' + (product.image || "") + '" alt="' + product.name + '">',
            "</div>",
            '<div class="admin-producto-info">',
            '<div class="admin-producto-meta">',
            '<span class="cond-card-label">SKU</span><span class="cond-card-value">' + product.sku + "</span>",
            '<span class="cond-card-label">Línea</span><span class="cond-card-value">' + product.line + "</span>",
            '<span class="cond-card-label">Contenido</span><span class="cond-card-value">' + product.content + "</span>",
            '<span class="cond-card-label">Precio base</span><span class="cond-card-value">' + money(product.price) + "</span>",
            '<span class="cond-card-label">Estado</span>',
            '<span class="cond-card-value"><span class="status-pill ' + (isActive ? "status-active" : "status-inactive") + '">' + (isActive ? "Activo" : "Inactivo") + "</span></span>",
            "</div>",
            '<button type="button" class="btn ' + (isActive ? "btn-soft" : "btn-primary") + ' btn-block" id="adminProductoToggleBtn" style="margin-top:0.75rem;">',
            (isActive ? "Desactivar producto" : "Activar producto"),
            "</button>",
            "<h4 style=\"margin-top:1rem;\">Precios por lista</h4>",
            '<table class="orders-table">',
            "<thead><tr><th>Lista de precios</th><th>Precio asignado</th></tr></thead>",
            "<tbody>" + (priceRows || '<tr><td colspan=\"2\" class=\"muted\">Sin listas configuradas.</td></tr>') + "</tbody>",
            "</table>",
            "</div>",
            "</div>",
            "</div>"
        ].join("");

        document.body.appendChild(modal);

        document.getElementById("adminProductoCloseBtn").addEventListener("click", function () {
            modal.remove();
        });

        modal.addEventListener("click", function (event) {
            if (event.target === modal) { modal.remove(); }
        });

        document.getElementById("adminProductoToggleBtn").addEventListener("click", function () {
            product.isActive = !product.isActive;
            writeStorage(state.data);
            showToast("Producto " + (product.isActive ? "activado" : "desactivado") + ".");
            modal.remove();
            renderAdminProductos();
        });
    }

    function renderAdminCondiciones() {
        const conditions = state.data.distributors[0].conditions;
        document.getElementById("view-admin-condiciones").innerHTML = [
            '<section class="page-head"><h2>Administración - Condiciones vigentes</h2><p>Actualiza condiciones comerciales aplicadas al demo.</p></section>',
            '<article class="card">',
            '<form id="adminConditionsForm" class="admin-form">',
            '<label for="adminMonth">Mes vigente</label><input id="adminMonth" value="' + conditions.monthLabel + '" required>',
            '<label for="adminDiscount">Descuento pronto pago (%)</label><input id="adminDiscount" type="number" min="0" max="100" value="' + conditions.financialDiscountPercent + '" required>',
            '<label for="adminCreditDays">Plazo crédito (días)</label><input id="adminCreditDays" type="number" min="1" value="' + conditions.creditDays + '" required>',
            '<label for="adminDispatch">Despacho (horas)</label><input id="adminDispatch" type="number" min="1" value="' + conditions.dispatchHours + '" required>',
            '<button class="btn btn-primary" type="submit">Guardar ajustes demo</button>',
            '</form>',
            '</article>'
        ].join("");

        document.getElementById("adminConditionsForm").addEventListener("submit", function (event) {
            event.preventDefault();
            const monthLabel = document.getElementById("adminMonth").value.trim();
            const financialDiscountPercent = Number(document.getElementById("adminDiscount").value);
            const creditDays = Number(document.getElementById("adminCreditDays").value);
            const dispatchHours = Number(document.getElementById("adminDispatch").value);

            state.data.distributors.forEach(function (d) {
                d.conditions.monthLabel = monthLabel;
                d.conditions.financialDiscountPercent = financialDiscountPercent;
                d.conditions.creditDays = creditDays;
                d.conditions.dispatchHours = dispatchHours;
            });

            writeStorage(state.data);
            showToast("Condiciones demo actualizadas para todos los distribuidores.");
        });
    }

    function openAdminCreateDistributorModal() {
        const adminConfig = getAdminConfig();
        const priceListOptions = adminConfig.priceListOptions || [];
        const modalId = "adminCreateDistributorModal";
        const existing = document.getElementById(modalId);
        if (existing) {
            existing.remove();
        }

        const modal = document.createElement("div");
        modal.id = modalId;
        modal.className = "admin-modal";
        modal.innerHTML = [
            '<div class="admin-modal-content">',
            '<div class="admin-modal-head">',
            '<h3>Crear distribuidor</h3>',
            '<button type="button" class="btn btn-ghost btn-xs" id="adminCreateCloseBtn">Cerrar</button>',
            '</div>',
            '<form id="adminCreateDistributorForm" class="admin-form">',
            '<label for="adminCreateName">Nombre</label><input id="adminCreateName" required>',
            '<label for="adminCreateUsername">Usuario</label><input id="adminCreateUsername" required>',
            '<label for="adminCreatePassword">Clave</label><input id="adminCreatePassword" value="demo123" required>',
            '<label for="adminCreateNit">NIT</label><input id="adminCreateNit" required>',
            '<label for="adminCreateCity">Ciudad</label><input id="adminCreateCity" required>',
            '<label for="adminCreateZone">Zona</label><input id="adminCreateZone" required>',
            '<label for="adminCreatePriceList">Lista de precios</label><select id="adminCreatePriceList">' + priceListOptions.map(function (option) {
                return '<option value="' + option + '">' + option + '</option>';
            }).join("") + '</select>',
            '<label for="adminCreateCreditLimit">Cupo asignado (COP)</label><input id="adminCreateCreditLimit" type="number" min="0" value="3000000" required>',
            '<button class="btn btn-primary" type="submit">Crear distribuidor</button>',
            '</form>',
            '</div>'
        ].join("");

        document.body.appendChild(modal);

        document.getElementById("adminCreateCloseBtn").addEventListener("click", function () {
            modal.remove();
        });

        modal.addEventListener("click", function (event) {
            if (event.target === modal) {
                modal.remove();
            }
        });

        document.getElementById("adminCreateDistributorForm").addEventListener("submit", function (event) {
            event.preventDefault();

            const username = document.getElementById("adminCreateUsername").value.trim().toLowerCase();
            const exists = state.data.distributors.some(function (d) { return d.username === username; });

            if (exists || username === window.DEMO_SEED.adminUser.username) {
                showToast("Ese usuario ya existe.");
                return;
            }

            const id = "dist_" + Date.now();
            const conditionTemplate = state.data.distributors[0] ? state.data.distributors[0].conditions : window.DEMO_SEED.conditions;

            const newDistributor = {
                id: id,
                username: username,
                password: document.getElementById("adminCreatePassword").value.trim(),
                name: document.getElementById("adminCreateName").value.trim(),
                nit: document.getElementById("adminCreateNit").value.trim(),
                city: document.getElementById("adminCreateCity").value.trim(),
                zone: document.getElementById("adminCreateZone").value.trim(),
                priceList: document.getElementById("adminCreatePriceList").value,
                creditLimit: Math.max(0, toNumber(document.getElementById("adminCreateCreditLimit").value)),
                portfolioBalance: 0,
                overdueBalance: 0,
                monthlyBudgetGoal: 3000000,
                monthlyExecutedBase: 0,
                serviceKpi: 90,
                isActive: true,
                conditions: clone(conditionTemplate),
                categoryExecutionBase: state.data.lines.reduce(function (acc, line) {
                    acc[line] = 0;
                    return acc;
                }, {})
            };

            state.data.distributors.push(newDistributor);
            state.data.ordersByDistributor[id] = [];
            state.data.cartByDistributor[id] = [];
            state.data.orderSequence[id] = 3000;
            writeStorage(state.data);

            modal.remove();
            showToast("Distribuidor creado exitosamente.");
            renderAdministrador();
        });
    }

    function openAdminDistributorEditor(distributorId) {
        const distributor = state.data.distributors.find(function (item) { return item.id === distributorId; });
        const adminConfig = getAdminConfig();

        if (!distributor) {
            showToast("Distribuidor no encontrado.");
            return;
        }

        const options = (adminConfig.priceListOptions || []).map(function (option) {
            const selected = distributor.priceList === option ? "selected" : "";
            return '<option value="' + option + '" ' + selected + '>' + option + '</option>';
        }).join("");

        const modalId = "adminDistributorEditorModal";
        const existing = document.getElementById(modalId);
        if (existing) {
            existing.remove();
        }

        const modal = document.createElement("div");
        modal.id = modalId;
        modal.className = "admin-modal";
        modal.innerHTML = [
            '<div class="admin-modal-content">',
            '<div class="admin-modal-head">',
            '<h3>Editar distribuidor</h3>',
            '<button type="button" class="btn btn-ghost btn-xs" id="adminEditCloseBtn">Cerrar</button>',
            '</div>',
            '<form id="adminDistributorEditForm" class="admin-form">',
            '<label for="adminEditName">Nombre</label><input id="adminEditName" value="' + distributor.name + '" required>',
            '<label for="adminEditCity">Ciudad</label><input id="adminEditCity" value="' + distributor.city + '" required>',
            '<label for="adminEditZone">Zona</label><input id="adminEditZone" value="' + distributor.zone + '" required>',
            '<label for="adminEditPriceList">Lista de precios</label><select id="adminEditPriceList">' + options + '</select>',
            '<label for="adminEditCreditLimit">Cupo asignado (COP)</label><input id="adminEditCreditLimit" type="number" min="0" value="' + distributor.creditLimit + '" required>',
            '<label for="adminEditPortfolio">Saldo cartera (COP)</label><input id="adminEditPortfolio" type="number" min="0" value="' + distributor.portfolioBalance + '" required>',
            '<label for="adminEditOverdue">Mora (COP)</label><input id="adminEditOverdue" type="number" min="0" value="' + distributor.overdueBalance + '" required>',
            '<button class="btn btn-primary" type="submit">Guardar cambios</button>',
            '</form>',
            '</div>'
        ].join("");

        document.body.appendChild(modal);

        document.getElementById("adminEditCloseBtn").addEventListener("click", function () {
            modal.remove();
        });

        modal.addEventListener("click", function (event) {
            if (event.target === modal) {
                modal.remove();
            }
        });

        document.getElementById("adminDistributorEditForm").addEventListener("submit", function (event) {
            event.preventDefault();
            distributor.name = document.getElementById("adminEditName").value.trim();
            distributor.city = document.getElementById("adminEditCity").value.trim();
            distributor.zone = document.getElementById("adminEditZone").value.trim();
            distributor.priceList = document.getElementById("adminEditPriceList").value;
            distributor.creditLimit = Math.max(0, toNumber(document.getElementById("adminEditCreditLimit").value));
            distributor.portfolioBalance = Math.max(0, toNumber(document.getElementById("adminEditPortfolio").value));
            distributor.overdueBalance = Math.max(0, toNumber(document.getElementById("adminEditOverdue").value));

            writeStorage(state.data);
            syncCartPricesByDistributor(distributor);
            modal.remove();
            showToast("Distribuidor actualizado correctamente.");
            renderAdministrador();
        });
    }

    function openAdminOrderEditor(distributorId, orderId) {
        const order = (state.data.ordersByDistributor[distributorId] || []).find(function (item) {
            return item.id === orderId;
        });

        if (!order) {
            showToast("Pedido no encontrado.");
            return;
        }

        const modalId = "adminOrderEditorModal";
        const existing = document.getElementById(modalId);
        if (existing) {
            existing.remove();
        }

        const statusOptions = window.DEMO_SEED.statusFlow.map(function (status) {
            const selected = normalizeOrderStatus(order.status) === status ? "selected" : "";
            return '<option value="' + status + '" ' + selected + '>' + status + "</option>";
        }).join("");

        const modal = document.createElement("div");
        modal.id = modalId;
        modal.className = "admin-modal";
        modal.innerHTML = [
            '<div class="admin-modal-content">',
            '<div class="admin-modal-head">',
            '<h3>Editar pedido ' + order.number + '</h3>',
            '<button type="button" class="btn btn-ghost btn-xs" id="adminOrderEditCloseBtn">Cerrar</button>',
            '</div>',
            '<form id="adminOrderEditForm" class="admin-form">',
            '<label for="adminOrderStatus">Estado</label><select id="adminOrderStatus">' + statusOptions + '</select>',
            '<label for="adminOrderDispatchType">Despacho final</label><select id="adminOrderDispatchType"><option value="completo" ' + (order.dispatchType !== "parcial" ? "selected" : "") + '>Completo</option><option value="parcial" ' + (order.dispatchType === "parcial" ? "selected" : "") + '>Parcial</option></select>',
            '<label for="adminOrderObservation">Observaciones administrativas</label><textarea id="adminOrderObservation" rows="4" placeholder="Ej. Se despacharon 6 de 10 unidades, saldo pendiente para siguiente ruta.">' + (order.adminObservation || "") + '</textarea>',
            '<button class="btn btn-primary" type="submit">Guardar pedido</button>',
            '</form>',
            '</div>'
        ].join("");

        document.body.appendChild(modal);

        document.getElementById("adminOrderEditCloseBtn").addEventListener("click", function () {
            modal.remove();
        });

        modal.addEventListener("click", function (event) {
            if (event.target === modal) {
                modal.remove();
            }
        });

        document.getElementById("adminOrderEditForm").addEventListener("submit", function (event) {
            event.preventDefault();
            const nextStatus = normalizeOrderStatus(document.getElementById("adminOrderStatus").value);
            const dispatchType = document.getElementById("adminOrderDispatchType").value;
            const adminObservation = document.getElementById("adminOrderObservation").value.trim();

            if (order.status !== nextStatus) {
                order.status = nextStatus;
                order.timeline = order.timeline || [];
                order.timeline.push({ status: nextStatus, at: new Date().toISOString() });
            }

            order.dispatchType = isFinalStatus(nextStatus) ? dispatchType : null;
            order.adminObservation = adminObservation;
            writeStorage(state.data);
            modal.remove();
            showToast("Pedido actualizado desde administración.");
            renderAdministrador();
        });
    }

    function csvSafe(value) {
        return '"' + String(value || "").replace(/"/g, '""') + '"';
    }

    function downloadOrdersReport(format, orders, reportTitle) {
        if (!orders || !orders.length) {
            showToast("No hay pedidos para exportar.");
            return;
        }

        const rows = orders.map(function (order) {
            return {
                number: order.number,
                date: new Date(order.createdAt).toLocaleString("es-CO"),
                total: money(order.total),
                status: normalizeOrderStatus(order.status),
                dispatch: isFinalStatus(order.status) ? (order.dispatchType === "parcial" ? "Parcial" : "Completo") : "-",
                observation: order.adminObservation || ""
            };
        });

        if (format === "excel") {
            const header = ["Pedido", "Fecha", "Valor", "Estado", "Despacho", "Observación"];
            const lines = [header.map(csvSafe).join(",")].concat(rows.map(function (row) {
                return [row.number, row.date, row.total, row.status, row.dispatch, row.observation].map(csvSafe).join(",");
            }));

            const blob = new Blob([lines.join("\n")], { type: "text/csv;charset=utf-8;" });
            const url = URL.createObjectURL(blob);
            const link = document.createElement("a");
            link.href = url;
            link.download = "reporte-pedidos.csv";
            link.click();
            URL.revokeObjectURL(url);
            return;
        }

        const htmlRows = rows.map(function (row) {
            return "<tr><td>" + row.number + "</td><td>" + row.date + "</td><td>" + row.total + "</td><td>" + row.status + "</td><td>" + row.dispatch + "</td><td>" + row.observation + "</td></tr>";
        }).join("");
        const printWindow = window.open("", "_blank");
        if (!printWindow) {
            showToast("No se pudo abrir la ventana para PDF.");
            return;
        }

        printWindow.document.write([
            "<html><head><title>Reporte de pedidos</title>",
            "<style>body{font-family:Arial,sans-serif;padding:18px;} h2{margin:0 0 8px;} table{width:100%;border-collapse:collapse;} th,td{border:1px solid #ccd5df;padding:6px;text-align:left;font-size:12px;} th{background:#f2f5f8;}</style>",
            "</head><body>",
            "<h2>" + reportTitle + "</h2>",
            "<p>Generado: " + new Date().toLocaleString("es-CO") + "</p>",
            "<table><thead><tr><th>Pedido</th><th>Fecha</th><th>Valor</th><th>Estado</th><th>Despacho</th><th>Observación</th></tr></thead><tbody>",
            htmlRows,
            "</tbody></table></body></html>"
        ].join(""));
        printWindow.document.close();
        printWindow.focus();
        printWindow.print();
    }

    function bindRouting() {
        window.addEventListener("hashchange", function () {
            if (!state.data.session) {
                return;
            }
            handleRouting();
        });
    }

    function start() {
        initState();
        setupAuth();
        bindRouting();
        syncUiBySession();

        if (!window.location.hash) {
            window.location.hash = isAdminSession() ? "#/administrador" : "#/inicio";
        }
    }

    start();
})();
