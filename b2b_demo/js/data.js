(function () {
    const products = [
        { sku: "EB-001", name: "Shampoo Tradicional", line: "Tradicional", price: 35000, content: "440 ML", image: "https://cdn.shopify.com/s/files/1/0575/8933/4187/files/SHAMPOO-TRADICIONAL.png?v=1725992422" },
        { sku: "EB-002", name: "Dúo Deluxe", line: "Tradicional", price: 51000, content: "Kit", image: "https://cdn.shopify.com/s/files/1/0575/8933/4187/files/COMBOS_Mesadetrabajo1copia5.png?v=1695223919" },
        { sku: "EB-003", name: "Mini Kit Tradicional", line: "Combos", price: 92000, content: "Kit", image: "https://cdn.shopify.com/s/files/1/0575/8933/4187/files/COMBOSNUEVOS_KITTRADI.png?v=1714493496" },
        { sku: "EB-004", name: "Shampoo Nutritivo", line: "Regenerador Intenso", price: 35000, content: "440 ML", image: "https://cdn.shopify.com/s/files/1/0575/8933/4187/files/SHAMPOOFRONTALnew.png?v=1709305602" },
        { sku: "EB-005", name: "Shampoo Protección Plus", line: "Protección Plus", price: 35000, content: "440 ML", image: "https://cdn.shopify.com/s/files/1/0575/8933/4187/files/SHAMPOO_NEW.png?v=1771261053" },
        { sku: "EB-006", name: "Termoprotector Protección Plus", line: "Protección Plus", price: 36000, content: "250 ML", image: "https://cdn.shopify.com/s/files/1/0575/8933/4187/files/TERMOPROTECTOR_NEW.png?v=1771261083" },
        { sku: "EB-007", name: "Acondicionador Rizos Perfectos", line: "Rizos y Ondas", price: 40000, content: "440 ML", image: "https://cdn.shopify.com/s/files/1/0575/8933/4187/files/ACONDICIONADORKIDSRIZOS.png?v=1748292263" },
        { sku: "EB-008", name: "Crema para Peinar Control Rizos", line: "Rizos y Ondas", price: 40000, content: "440 ML", image: "https://cdn.shopify.com/s/files/1/0575/8933/4187/files/CREMA_PARA_PEINAR_KIDS_RIZOS.png?v=1748293910" },
        { sku: "EB-009", name: "Gelatina Slime Definidora", line: "Rizos y Ondas", price: 48000, content: "440 ML", image: "https://cdn.shopify.com/s/files/1/0575/8933/4187/files/GELATINADEFINIDORAKIDSRIZOS.png?v=1748295455" },
        { sku: "EB-010", name: "Co-Wash", line: "Rizos y Ondas", price: 42000, content: "440 ML", image: "https://cdn.shopify.com/s/files/1/0575/8933/4187/files/COWASHFRONTAL.png?v=1720138049" },
        { sku: "EB-011", name: "Óleo Rizos y Ondas", line: "Rizos y Ondas", price: 25000, content: "35 ML", image: "https://cdn.shopify.com/s/files/1/0575/8933/4187/files/OLEOCONCAJA2.png?v=1705960494" },
        { sku: "EB-012", name: "Agua de Coco (Activador de Rizos)", line: "Rizos y Ondas", price: 27000, content: "250 ML", image: "https://cdn.shopify.com/s/files/1/0575/8933/4187/files/AGUA_DE_COCO.png?v=1725026083" },
        { sku: "EB-013", name: "Dúo Hidratación", line: "Rizos y Ondas", price: 51000, content: "Kit", image: "https://cdn.shopify.com/s/files/1/0575/8933/4187/files/COMBOS_Mesadetrabajo1copia8_5e5c9a5a-0cb3-4167-881e-88d96c3c51c7.png?v=1695223940" },
        { sku: "EB-014", name: "Mini Kit Rizos y Ondas", line: "Combos", price: 94000, content: "Kit", image: "https://cdn.shopify.com/s/files/1/0575/8933/4187/files/COMBOS_NUEVOS_KIT_RIZOS_Y_ONDAS_KIT_RIZOS_Y_ONDAS_1.png?v=1741617966" },
        { sku: "EB-015", name: "Shampoo + Acondicionador Niños (2en1)", line: "Kids", price: 35000, content: "440 ML", image: "https://cdn.shopify.com/s/files/1/0575/8933/4187/files/SHAMPOOYACONDICIONADORFRONTAL_ada9837a-52e2-4a2f-994d-32073109e060.png?v=1726608264" },
        { sku: "EB-016", name: "Acondicionador para Niñas", line: "Kids", price: 35000, content: "440 ML", image: "https://cdn.shopify.com/s/files/1/0575/8933/4187/files/AcondicionadorKids.png?v=1691708039" },
        { sku: "EB-017", name: "Combo Boys", line: "Kids", price: 68000, content: "Kit", image: "https://cdn.shopify.com/s/files/1/0575/8933/4187/files/COMBOBOYS.png?v=1702933948" },
        { sku: "EB-018", name: "Mascarilla WOW", line: "Especial", price: 92000, content: "250 ML", image: "https://cdn.shopify.com/s/files/1/0575/8933/4187/files/MASCARILLA-WOW.png?v=1739231634" },
        { sku: "EB-019", name: "Splash Capilar Sweet", line: "Especial", price: 26500, content: "250 ML", image: "https://cdn.shopify.com/s/files/1/0575/8933/4187/files/SPLASHCAPILARNINASFRONTALNUEVO.png?v=1763156263" },
        { sku: "EB-020", name: "Finish Chia Oil", line: "Especial", price: 25000, content: "35 ML", image: "https://cdn.shopify.com/s/files/1/0575/8933/4187/files/FINISHFRONTALCAJA.png?v=1706109338" }
    ];

    const baseConditions = {
        monthLabel: "junio 2026",
        financialDiscountPercent: 5,
        earlyPaymentDays: 30,
        creditDays: 60,
        dispatchHours: 72
    };

    const distributors = [
        {
            id: "bella",
            username: "bella",
            password: "demo123",
            name: "Distribuciones Bella S.A.S.",
            nit: "901.234.567-8",
            city: "Medellín",
            zone: "Antioquia",
            priceList: "Mayorista A",
            creditLimit: 8000000,
            portfolioBalance: 3250000,
            overdueBalance: 0,
            monthlyBudgetGoal: 6000000,
            monthlyExecutedBase: 2150000,
            serviceKpi: 92,
            conditions: baseConditions,
            categoryExecutionBase: {
                "Tradicional": 420000,
                "Regenerador Intenso": 250000,
                "Protección Plus": 300000,
                "Rizos y Ondas": 620000,
                "Kids": 290000,
                "Especial": 170000,
                "Combos": 100000
            }
        },
        {
            id: "cali",
            username: "cali",
            password: "demo123",
            name: "Salón & Estilo Cali",
            nit: "900.987.654-3",
            city: "Cali",
            zone: "Valle",
            priceList: "Mayorista B",
            creditLimit: 5000000,
            portfolioBalance: 4600000,
            overdueBalance: 320000,
            monthlyBudgetGoal: 4000000,
            monthlyExecutedBase: 3480000,
            serviceKpi: 81,
            conditions: baseConditions,
            categoryExecutionBase: {
                "Tradicional": 580000,
                "Regenerador Intenso": 360000,
                "Protección Plus": 520000,
                "Rizos y Ondas": 1020000,
                "Kids": 420000,
                "Especial": 400000,
                "Combos": 180000
            }
        }
    ];

    function pickProduct(sku) {
        return products.find(function (p) { return p.sku === sku; });
    }

    function makeLine(sku, qty) {
        const product = pickProduct(sku);
        return {
            sku: product.sku,
            name: product.name,
            line: product.line,
            price: product.price,
            quantity: qty,
            subtotal: product.price * qty
        };
    }

    function orderFromOffset(number, offsetHours, status, lines) {
        const createdAt = new Date(Date.now() - offsetHours * 60 * 60 * 1000).toISOString();
        const value = lines.reduce(function (sum, line) { return sum + line.subtotal; }, 0);

        return {
            id: number,
            number: number,
            createdAt: createdAt,
            status: status,
            lines: lines,
            total: value,
            isDemoCreated: false,
            timeline: [
                { status: "Registrado", at: createdAt }
            ]
        };
    }

    const seededOrdersByDistributor = {
        bella: [
            orderFromOffset("PED-1042", 1, "Registrado", [makeLine("EB-003", 5), makeLine("EB-019", 15)]),
            orderFromOffset("PED-1039", 20, "Recepcionado Siesa", [makeLine("EB-002", 10), makeLine("EB-007", 15)]),
            orderFromOffset("PED-1031", 48, "En alistamiento", [makeLine("EB-005", 10), makeLine("EB-013", 10)]),
            orderFromOffset("PED-1024", 120, "Facturado y despachado", [makeLine("EB-018", 10), makeLine("EB-014", 12)]),
            orderFromOffset("PED-1018", 216, "Facturado y despachado", [makeLine("EB-001", 8), makeLine("EB-011", 12)])
        ],
        cali: [
            orderFromOffset("PED-2048", 4, "Registrado", [makeLine("EB-017", 8), makeLine("EB-019", 10)]),
            orderFromOffset("PED-2043", 29, "Recepcionado Siesa", [makeLine("EB-009", 10), makeLine("EB-006", 10)]),
            orderFromOffset("PED-2036", 79, "Recepcionado Siesa", [makeLine("EB-008", 15), makeLine("EB-013", 8)]),
            orderFromOffset("PED-2031", 140, "Facturado y despachado", [makeLine("EB-015", 18), makeLine("EB-020", 10)]),
            orderFromOffset("PED-2022", 240, "Facturado y despachado", [makeLine("EB-004", 20), makeLine("EB-010", 14)])
        ]
    };

    const adminUser = {
        id: "admin",
        username: "admin",
        password: "demo123",
        name: "Administrador Eurobelleza"
    };

    window.DEMO_SEED = {
        products: products,
        distributors: distributors,
        seededOrdersByDistributor: seededOrdersByDistributor,
        conditions: baseConditions,
        adminUser: adminUser,
        statusFlow: ["Registrado", "Recepcionado Siesa", "En alistamiento", "Facturado y despachado"],
        lines: ["Tradicional", "Regenerador Intenso", "Protección Plus", "Rizos y Ondas", "Kids", "Especial", "Combos"]
    };
})();
