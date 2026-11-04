import { AdminSection } from "@/components/staff/admin-nav";

export default function AdminLayout({ children }: LayoutProps<"/[lang]/admin">) {
  return <AdminSection>{children}</AdminSection>;
}
