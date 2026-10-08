import {
    Activity,
    ArrowDownLeft,
    ArrowUpRight,
    Banknote,
    Bell,
    BookOpen,
    Building2,
    CalendarClock,
    Circle,
    Command,
    Database,
    DatabaseZap,
    FileText,
    FlaskConical,
    Gauge,
    KeyRound,
    Layers,
    LayoutDashboard,
    LayoutGrid,
    Link2,
    Mail,
    MessagesSquare,
    Network,
    Newspaper,
    Package,
    Plus,
    Radio,
    RefreshCw,
    ScrollText,
    Send,
    Signal,
    Sparkles,
    Truck,
    Users,
    Wrench,
    X,
} from 'lucide-react'

import { cn } from '../lib/cn'

/**
 * Static icon registry for the Inertia panels — nav icons are declared server-side as
 * kebab-case names. Add a nav icon → add a line here. Keeps the bundle to the
 * icons actually used rather than lucide's full dynamic map.
 */
const ICONS = {
    'layout-grid': LayoutGrid,
    'arrow-up-right': ArrowUpRight,
    'arrow-down-left': ArrowDownLeft,
    'scroll-text': ScrollText,
    layers: Layers,
    'calendar-clock': CalendarClock,
    gauge: Gauge,
    mail: Mail,
    activity: Activity,
    'building-2': Building2,
    'key-round': KeyRound,
    'database-zap': DatabaseZap,
    'book-open': BookOpen,
    'flask-conical': FlaskConical,
    'layout-dashboard': LayoutDashboard,
    package: Package,
    radio: Radio,
    users: Users,
    banknotes: Banknote,
    truck: Truck,
    newspaper: Newspaper,
    wrench: Wrench,
    command: Command,
    signal: Signal,
    database: Database,
    'file-text': FileText,
    network: Network,
    link: Link2,
    'link-2': Link2,
    bell: Bell,
    sparkles: Sparkles,
    'messages-square': MessagesSquare,
    plus: Plus,
    x: X,
    send: Send,
    'refresh-cw': RefreshCw,
}

export function Icon({ name, className }) {
    const Glyph = ICONS[name] ?? Circle

    return <Glyph className={cn('size-4 shrink-0', className)} />
}
