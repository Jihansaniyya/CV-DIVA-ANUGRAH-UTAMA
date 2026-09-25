import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { Input, Select } from '@/components/ui/Field'
import { ConfirmDialog, Modal } from '@/components/ui/Modal'
import { Pagination } from '@/components/ui/Pagination'
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/State'
import { Table, TableWrap, Td, Th } from '@/components/ui/Table'
import { useRoles, useUsers } from '@/hooks/queries'
import { useToast } from '@/hooks/useToast'
import { errorValidasi, pesanError } from '@/lib/api'
import { userService, type UserPayload } from '@/services/userService'
import type { User } from '@/types'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Pencil, Plus, Power, Search, Trash2 } from 'lucide-react'
import { useEffect, useState, type FormEvent } from 'react'

const KOSONG: UserPayload = {
  name: '',
  username: '',
  email: '',
  phone: '',
  password: '',
  password_confirmation: '',
  role_id: 0,
  is_active: true,
}

export function UsersPage() {
  const toast = useToast()
  const queryClient = useQueryClient()

  const [page, setPage] = useState(1)
  const [q, setQ] = useState('')
  const [role, setRole] = useState('')
  const [formTerbuka, setFormTerbuka] = useState(false)
  const [diedit, setDiedit] = useState<User | null>(null)
  const [dihapus, setDihapus] = useState<User | null>(null)
  const [form, setForm] = useState<UserPayload>(KOSONG)
  const [errors, setErrors] = useState<Record<string, string>>({})

  const filter = { page, per_page: 10, q: q || undefined, role: role || undefined }
  const { data, isLoading, error, refetch } = useUsers(filter)
  const { data: roles } = useRoles()

  useEffect(() => {
    if (!formTerbuka) return

    setErrors({})
    setForm(
      diedit
        ? {
            name: diedit.name,
            username: diedit.username,
            email: diedit.email ?? '',
            phone: diedit.phone ?? '',
            password: '',
            password_confirmation: '',
            role_id: diedit.role?.id ?? 0,
            is_active: diedit.is_active,
          }
        : { ...KOSONG, role_id: roles?.[0]?.id ?? 0 },
    )
  }, [formTerbuka, diedit, roles])

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['users'] })

  const simpan = useMutation({
    mutationFn: (payload: UserPayload) => (diedit ? userService.update(diedit.id, payload) : userService.create(payload)),
    onSuccess: async () => {
      toast.sukses(diedit ? 'Data pengguna berhasil diperbarui.' : 'Pengguna berhasil ditambahkan.')
      await invalidate()
      setFormTerbuka(false)
    },
    onError: (err) => {
      const validasi = errorValidasi(err)
      setErrors(validasi)
      toast.gagal(Object.keys(validasi).length > 0 ? 'Periksa kembali data yang kamu masukkan.' : pesanError(err))
    },
  })

  const hapus = useMutation({
    mutationFn: (id: number) => userService.remove(id),
    onSuccess: async () => {
      toast.sukses('Pengguna berhasil dihapus.')
      setDihapus(null)
      await invalidate()
    },
    onError: (err) => {
      toast.gagal(pesanError(err))
      setDihapus(null)
    },
  })

  const toggle = useMutation({
    mutationFn: (id: number) => userService.toggleActive(id),
    onSuccess: async (user) => {
      toast.sukses(user.is_active ? 'Akun berhasil diaktifkan.' : 'Akun berhasil dinonaktifkan.')
      await invalidate()
    },
    onError: (err) => toast.gagal(pesanError(err)),
  })

  const kirim = (event: FormEvent) => {
    event.preventDefault()
    const validasi: Record<string, string> = {}

    if (!form.name.trim()) validasi.name = 'Nama wajib diisi.'
    if (!form.username.trim()) validasi.username = 'Nama pengguna wajib diisi.'
    if (!form.role_id) validasi.role_id = 'Peran wajib dipilih.'
    if (!diedit && !(form.email ?? '').trim()) validasi.email = 'Email wajib diisi.'
    if (!diedit && !(form.phone ?? '').trim()) validasi.phone = 'Nomor telepon wajib diisi.'
    if (!diedit && (form.password ?? '').length < 8) validasi.password = 'Kata sandi minimal 8 karakter.'
    if ((form.password ?? '') !== (form.password_confirmation ?? '')) {
      validasi.password_confirmation = 'Konfirmasi kata sandi tidak sama.'
    }

    setErrors(validasi)

    if (Object.keys(validasi).length > 0) return

    const payload: UserPayload = { ...form, role_id: Number(form.role_id) }

    if (diedit && !payload.password) {
      delete payload.password
      delete payload.password_confirmation
    }

    simpan.mutate(payload)
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="text-lg font-semibold text-ink">Data Pengguna</h2>
          <p className="text-xs text-muted">Kelola akun Admin, QS, dan Kontraktor beserta hak aksesnya.</p>
        </div>
        <Button
          icon={<Plus className="size-4" />}
          onClick={() => {
            setDiedit(null)
            setFormTerbuka(true)
          }}
        >
          Tambah Pengguna
        </Button>
      </div>

      <Card bodyClassName="pt-4">
        <div className="flex flex-col gap-3 sm:flex-row">
          <div className="relative flex-1">
            <Search className="pointer-events-none absolute top-[11px] left-3 size-4 text-muted" aria-hidden />
            <Input
              placeholder="Cari nama atau username..."
              className="pl-9"
              value={q}
              onChange={(event) => {
                setQ(event.target.value)
                setPage(1)
              }}
              aria-label="Cari pengguna"
            />
          </div>
          <Select
            value={role}
            onChange={(event) => {
              setRole(event.target.value)
              setPage(1)
            }}
            aria-label="Filter peran"
            wrapClassName="sm:w-48"
          >
            <option value="">Semua Peran</option>
            <option value="ADMIN">Admin</option>
            <option value="QS">Quantity Surveyor</option>
            <option value="KONTRAKTOR">Kontraktor</option>
          </Select>
        </div>

        <div className="mt-4">
          {isLoading ? (
            <LoadingState />
          ) : error ? (
            <ErrorState pesan={pesanError(error)} onRetry={() => void refetch()} />
          ) : !data || data.data.length === 0 ? (
            <EmptyState judul="Pengguna tidak ditemukan" />
          ) : (
            <>
              <TableWrap>
                <Table>
                  <thead>
                    <tr>
                      <Th>No.</Th>
                      <Th>Nama</Th>
                      <Th>Username</Th>
                      <Th>Role</Th>
                      <Th align="center">Status</Th>
                      <Th align="center">Aksi</Th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.data.map((user, index) => (
                      <tr key={user.id} className="hover:bg-surface/60">
                        <Td>{(data.meta.from ?? 1) + index}</Td>
                        <Td>
                          <p className="font-medium text-ink">{user.name}</p>
                          {user.email && <p className="text-[11px] text-muted">{user.email}</p>}
                        </Td>
                        <Td className="text-muted">@{user.username}</Td>
                        <Td>
                          <Badge tone={user.role_code === 'ADMIN' ? 'info' : user.role_code === 'QS' ? 'warning' : 'neutral'}>
                            {user.role?.name ?? user.role_code}
                          </Badge>
                        </Td>
                        <Td align="center">
                          <Badge tone={user.is_active ? 'success' : 'neutral'}>{user.is_active ? 'Aktif' : 'Nonaktif'}</Badge>
                        </Td>
                        <Td align="center">
                          <div className="flex justify-center gap-1">
                            <button
                              type="button"
                              aria-label={user.is_active ? 'Nonaktifkan akun' : 'Aktifkan akun'}
                              className="rounded-lg p-1.5 text-muted hover:bg-surface hover:text-navy"
                              onClick={() => toggle.mutate(user.id)}
                            >
                              <Power className="size-4" aria-hidden />
                            </button>
                            <button
                              type="button"
                              aria-label="Ubah pengguna"
                              className="rounded-lg p-1.5 text-muted hover:bg-surface hover:text-navy"
                              onClick={() => {
                                setDiedit(user)
                                setFormTerbuka(true)
                              }}
                            >
                              <Pencil className="size-4" aria-hidden />
                            </button>
                            <button
                              type="button"
                              aria-label="Hapus pengguna"
                              className="rounded-lg p-1.5 text-muted hover:bg-danger-soft hover:text-danger"
                              onClick={() => setDihapus(user)}
                            >
                              <Trash2 className="size-4" aria-hidden />
                            </button>
                          </div>
                        </Td>
                      </tr>
                    ))}
                  </tbody>
                </Table>
              </TableWrap>

              <Pagination
                page={data.meta.current_page}
                lastPage={data.meta.last_page}
                total={data.meta.total}
                from={data.meta.from}
                to={data.meta.to}
                onChange={setPage}
              />
            </>
          )}
        </div>
      </Card>

      <Modal
        open={formTerbuka}
        onClose={() => setFormTerbuka(false)}
        title={diedit ? 'Ubah Pengguna' : 'Tambah Pengguna'}
        footer={
          <>
            <Button variant="outline" onClick={() => setFormTerbuka(false)} disabled={simpan.isPending}>
              Batal
            </Button>
            <Button form="form-pengguna" type="submit" loading={simpan.isPending}>
              Simpan
            </Button>
          </>
        }
      >
        <form id="form-pengguna" className="grid gap-4 sm:grid-cols-2" onSubmit={kirim} noValidate>
          <Input label="Nama Lengkap" required value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} error={errors.name} />
          <Input
            label="Username"
            required
            value={form.username}
            onChange={(event) => setForm({ ...form, username: event.target.value })}
            error={errors.username}
            hint="Hanya huruf, angka, garis bawah, dan tanda hubung."
          />
          <Input
            label="Email"
            type="email"
            required={!diedit}
            value={form.email ?? ''}
            onChange={(event) => setForm({ ...form, email: event.target.value })}
            error={errors.email}
            placeholder="Contoh: nisa@divaanugrah.co.id"
          />
          <Input
            label="Nomor Telepon"
            type="tel"
            required={!diedit}
            value={form.phone ?? ''}
            onChange={(event) => setForm({ ...form, phone: event.target.value })}
            error={errors.phone}
            placeholder="Contoh: 0812 3456 7890"
          />
          <Input
            label={diedit ? 'Kata Sandi Baru' : 'Kata Sandi'}
            type="password"
            required={!diedit}
            value={form.password ?? ''}
            onChange={(event) => setForm({ ...form, password: event.target.value })}
            error={errors.password}
            hint={diedit ? 'Kosongkan bila tidak ingin mengubah kata sandi.' : 'Minimal 8 karakter.'}
          />
          <Input
            label="Konfirmasi Kata Sandi"
            type="password"
            required={!diedit}
            value={form.password_confirmation ?? ''}
            onChange={(event) => setForm({ ...form, password_confirmation: event.target.value })}
            error={errors.password_confirmation}
          />
          <Select label="Peran" required value={form.role_id || ''} onChange={(event) => setForm({ ...form, role_id: Number(event.target.value) })} error={errors.role_id}>
            <option value="">Pilih peran</option>
            {roles?.map((item) => (
              <option key={item.id} value={item.id}>
                {item.name}
              </option>
            ))}
          </Select>
          <Select
            label="Status Akun"
            value={form.is_active ? '1' : '0'}
            onChange={(event) => setForm({ ...form, is_active: event.target.value === '1' })}
            error={errors.is_active}
          >
            <option value="1">Aktif</option>
            <option value="0">Nonaktif</option>
          </Select>
        </form>
      </Modal>

      <ConfirmDialog
        open={Boolean(dihapus)}
        title="Hapus Pengguna"
        pesan={`Akun "${dihapus?.name ?? ''}" akan dihapus permanen. Lanjutkan?`}
        loading={hapus.isPending}
        onConfirm={() => dihapus && hapus.mutate(dihapus.id)}
        onClose={() => setDihapus(null)}
      />
    </div>
  )
}
