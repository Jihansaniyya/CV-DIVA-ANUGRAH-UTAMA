import { Card } from '@/components/ui/Card'
import { EmptyState, LoadingState } from '@/components/ui/State'
import { Table, TableWrap, Td, Th } from '@/components/ui/Table'
import { useRoles, useUnits } from '@/hooks/queries'
import { useAuth } from '@/hooks/useAuth'

/** Master data aplikasi: peran pengguna dan satuan pekerjaan. */
export function SettingsPage() {
  const { user } = useAuth()
  const { data: roles, isLoading: memuatRoles } = useRoles()
  const { data: units, isLoading: memuatUnits } = useUnits()

  return (
    <div className="flex flex-col gap-4">
      <Card title="Profil Akun">
        <dl className="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
          <div>
            <dt className="text-xs text-muted">Nama</dt>
            <dd>{user?.name}</dd>
          </div>
          <div>
            <dt className="text-xs text-muted">Username</dt>
            <dd>@{user?.username}</dd>
          </div>
          <div>
            <dt className="text-xs text-muted">Email</dt>
            <dd>{user?.email ?? '-'}</dd>
          </div>
          <div>
            <dt className="text-xs text-muted">Peran</dt>
            <dd>{user?.role?.name ?? user?.role_code}</dd>
          </div>
        </dl>
      </Card>

      <Card title="Peran Pengguna" description="Hak akses aplikasi ditentukan oleh peran berikut." bodyClassName="pt-0">
        {memuatRoles ? (
          <LoadingState />
        ) : !roles || roles.length === 0 ? (
          <EmptyState />
        ) : (
          <TableWrap>
            <Table>
              <thead>
                <tr>
                  <Th>Kode</Th>
                  <Th>Nama Peran</Th>
                </tr>
              </thead>
              <tbody>
                {roles.map((role) => (
                  <tr key={role.id}>
                    <Td className="font-medium">{role.code}</Td>
                    <Td className="text-muted">{role.name}</Td>
                  </tr>
                ))}
              </tbody>
            </Table>
          </TableWrap>
        )}
      </Card>

      <Card title="Satuan Pekerjaan" description="Master data satuan yang dipakai pada data pekerjaan." bodyClassName="pt-0">
        {memuatUnits ? (
          <LoadingState />
        ) : !units || units.length === 0 ? (
          <EmptyState />
        ) : (
          <TableWrap>
            <Table>
              <thead>
                <tr>
                  <Th>Kode</Th>
                  <Th>Nama Satuan</Th>
                </tr>
              </thead>
              <tbody>
                {units.map((unit) => (
                  <tr key={unit.id}>
                    <Td className="font-medium">{unit.code}</Td>
                    <Td className="text-muted">{unit.name}</Td>
                  </tr>
                ))}
              </tbody>
            </Table>
          </TableWrap>
        )}
      </Card>
    </div>
  )
}
